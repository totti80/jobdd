<?php

use App\Models\Company;
use App\Models\JobFact;
use App\Models\JobPosting;
use App\Models\JobPublishedProfile;
use App\Models\UserQuery;
use App\Services\JobDecisionUseCaseService;
use App\Services\JobDiscoveryService;
use App\Services\JobFitService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function decisionPageFixture(int $count = 23): UserQuery
{
    $query = UserQuery::create(['public_id' => (string) Str::uuid(), 'session_token' => 'private-session-value',
        'raw_text' => 'PRIVATE RAW', 'occupation' => '機械設計', 'region' => '兵庫県', 'salary_min' => 600,
        'detailed_skills' => ['autocad']]);
    $company = Company::create(['name' => '比較会社 <script>alert(1)</script>']);
    for ($i = 0; $i < $count; $i++) {
        $job = JobPosting::create(['company_id' => $company->id, 'title' => '機械設計 '.$i.' <script>alert(2)</script>',
            'occupation' => '機械設計', 'region' => $i % 2 ? '大阪府' : '兵庫県',
            'salary_min' => $i % 3 ? 700 : null, 'salary_max' => null,
            'source_url' => 'https://careers.sample-company.jp/jobs/'.$i, 'provider_key' => 'sample',
            'description' => $i % 3 ? '【使用ツール】AutoCAD' : '【応募条件】AutoCADの経験 <script>alert(3)</script>']);
        if ($i % 4 !== 0) {
            JobFact::create(['job_posting_id' => $job->id, 'fact_category' => 'tool', 'fact_key' => 'autocad',
                'fact_value' => 'AutoCAD', 'normalized_value' => 'AutoCAD', 'extraction_method' => 'rule',
                'verification_status' => 'verified', 'observed_at' => '2026-09-19 00:00:00',
                'evidence_text' => $job->description]);
        }
    }

    return $query;
}

function decisionPageUrl(UserQuery $query, array $parameters = []): string
{
    return route('query.jobs', ['userQuery' => $query->public_id, ...$parameters]);
}

// Expected order comes from the existing full Fit results, independently of the lightweight sorter.
function decisionFitOrder(UserQuery $query, array $tools = [], ?array $ids = null): array
{
    $jobs = JobPosting::forPublic()->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))->with('jobFacts')->get();
    $requirements = ['desired' => array_map(fn ($key) => ['fact_key' => $key, 'action' => 'use'], $tools)];

    return $jobs->sortBy(function ($job) use ($query, $requirements) {
        $summary = app(JobFitService::class)->evaluate($query, $job, $job->jobFacts->all(), $requirements)['summary'];

        return [$summary['confirmed_mismatches'], -$summary['confirmed_matches'], $summary['unknowns'], $job->id];
    })->modelKeys();
}

test('decision page renders the authorized sorted pipeline with six reads and no writes', function () {
    $query = decisionPageFixture();
    $expected = array_slice(decisionFitOrder($query, ['autocad']), 0, 20);
    $sql = [];
    $record = true;
    DB::listen(function ($event) use (&$sql, &$record) {
        if ($record) {
            $sql[] = $event->sql;
        }
    });
    try {
        $response = $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token])
            ->get(decisionPageUrl($query, ['tools' => ['autocad']]));
        $response->assertOk()->assertSee('求人候補を確認・比較する')->assertSee('確認できた')
            ->assertSee('条件と異なる')->assertSee('未確認')->assertSee('詳細を見る')
            ->assertSee('該当求人')->assertSee('次の求人を見る（21〜23件）')
            ->assertDontSee('根拠を見る')->assertDontSee('求人元を見る')
            ->assertDontSee('担当業務での使用は確認できません。');
    } finally {
        $record = false;
    }
    expect($sql)->toHaveCount(6)->and(array_filter($sql, fn ($s) => ! preg_match('/^select\b/i', $s)))->toBe([]);
    $data = $response->viewData('items');
    expect(array_map(fn ($item) => $item['job']->id, $data))->toBe($expected)->and($data)->toHaveCount(20)
        ->and($response->viewData('total'))->toBe(23);
    foreach ($data as $item) {
        expect($item['fit']['job_posting_id'])->toBe($item['job']->id)
            ->and($item['fit']['summary']['hard_mismatch_keys'])->toBe([])
            ->and($item['job']->relationLoaded('company'))->toBeFalse();
        foreach ($item['fit']['axes'] as $axis) {
            expect($axis['requirement']['hard'])->toBeFalse();
            foreach ($axis['evidence'] as $evidence) {
                if ($evidence['kind'] === 'job_fact') {
                    expect(in_array($evidence['job_fact_id'], $item['job']->jobFacts->modelKeys(), true))->toBeTrue();
                }
            }
        }
    }
    $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertSee('&lt;script&gt;alert(2)&lt;/script&gt;', false)
        ->assertDontSee('&lt;script&gt;alert(3)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(', false)->assertDontSee('PRIVATE RAW')->assertDontSee('private-session-value')
        ->assertDontSee('TOP3')->assertDontSee('score')->assertDontSee('不適合')->assertDontSee('×')
        ->assertSee('rel="noopener noreferrer"', false)->assertSee('tools%5B0%5D=autocad', false);
});

test('decision pagination keeps tools and evaluates only twenty jobs', function () {
    $query = decisionPageFixture();
    $expected = array_slice(decisionFitOrder($query, ['solidworks', 'autocad']), 20);
    $response = $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token])
        ->get(decisionPageUrl($query, ['page' => 2, 'tools' => ['solidworks', 'autocad']]));
    $response->assertOk()->assertSee('前の求人を見る（1〜20件）')->assertDontSee('次の求人を見る（')->assertSee('tools%5B0%5D=solidworks', false);
    expect($response->viewData('items'))->toHaveCount(3)
        ->and(array_map(fn ($i) => $i['job']->id, $response->viewData('items')))->toBe($expected)
        ->and($response->viewData('pagination'))->toBe(['page' => 2, 'has_previous' => true, 'has_next' => false]);
});

test('decision page never infers tools from saved skills and shows empty pages safely', function () {
    $query = decisionPageFixture(1);
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token]);
    $response = $this->get(decisionPageUrl($query));
    $response->assertOk();
    expect($response->viewData('selected_tools'))->toBe([])
        ->and(array_column($response->viewData('items')[0]['fit']['axes'], 'key'))->toBe(['occupation', 'region', 'salary']);
    $this->get(decisionPageUrl($query, ['page' => 2]))->assertOk()->assertSee('このページに表示できる求人候補はありません。');
});

test('decision page rejects malformed GET inputs', function ($input) {
    $query = decisionPageFixture(0);
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token])
        ->get(decisionPageUrl($query, $input))->assertStatus(422);
})->with([
    [['page' => 0]], [['page' => -1]], [['page' => 'abc']], [['page' => '1.5']], [['page' => ['1']]],
    [['page' => '999999999999999999999999']], [['tools' => 'autocad']], [['tools' => ['plc']]],
    [['tools' => ['autocad', 'autocad']]], [['tools' => ['x' => 'autocad']]], [['tools' => [['autocad']]]],
]);

test('decision route preserves session protection and public ID binding', function () {
    $query = decisionPageFixture(0);
    $this->get(decisionPageUrl($query))->assertNotFound();
    $this->withSession(['jobdd_query_token_'.$query->public_id => 'wrong'])->get(decisionPageUrl($query))->assertNotFound();
    $this->get('/query/nonexistent/jobs')->assertNotFound();
    $this->get('/query/'.$query->id.'/jobs')->assertNotFound();
    $query->update(['session_token' => '']);
    $this->withSession(['jobdd_query_token_'.$query->public_id => ''])->get(decisionPageUrl($query))->assertNotFound();
});

test('decision use case preserves hard mismatches if explicitly supplied and batches companies', function () {
    $query = decisionPageFixture();
    $before = $query->getAttributes();
    $useCase = app(JobDecisionUseCaseService::class);
    $result = $useCase->run($query, 1, ['desired' => [['fact_key' => 'autocad', 'action' => 'use']], 'hard_axes' => ['region']]);
    expect($result['items'])->toHaveCount(20)->and($result['pagination']['has_next'])->toBeTrue()
        ->and(array_filter($result['items'], fn ($i) => $i['fit']['summary']['hard_mismatch_keys'] !== []))->not->toBeEmpty()
        ->and(array_filter($result['items'], fn ($i) => $i['job']->jobFacts->isEmpty()))->not->toBeEmpty()
        ->and($result['query'])->not->toHaveKeys(['raw_text', 'session_token'])->and($query->getAttributes())->toBe($before);
});

test('decision use case has no next page for exactly twenty jobs', function () {
    $query = decisionPageFixture(20);
    expect(app(JobDecisionUseCaseService::class)->run($query)['pagination']['has_next'])->toBeFalse();
});

test('decision page reads database sessions without updates or garbage collection', function () {
    $query = decisionPageFixture(1);
    config(['session.driver' => 'database', 'session.lottery' => [100, 100]]);
    app('session')->forgetDrivers();
    $session = app('session')->driver();
    $session->start();
    $session->put('jobdd_query_token_'.$query->public_id, $query->session_token);
    $session->save(); // Testing DB fixture only, before measurement.
    DB::table('sessions')->insert(['id' => str_repeat('a', 40), 'payload' => base64_encode(serialize([])), 'last_activity' => 1]);
    $before = DB::table('sessions')->orderBy('id')->get()->toJson();
    $sql = [];
    $record = true;
    DB::listen(function ($e) use (&$sql, &$record) {
        if ($record) {
            $sql[] = $e->sql;
        }
    });
    try {
        $this->withCookie($session->getName(), $session->getId())->get(decisionPageUrl($query))->assertOk();
    } finally {
        $record = false;
    }
    expect($sql)->toHaveCount(6)->and(array_filter($sql, fn ($s) => ! preg_match('/^select\b/i', $s)))->toBe([])
        ->and(DB::table('sessions')->orderBy('id')->get()->toJson())->toBe($before);
});

test('display total counts only eligible public candidates before slicing with unchanged SQL and order', function () {
    $query = decisionPageFixture(25);
    $jobs = JobPosting::orderBy('id')->get();
    $expected = $jobs->filter(fn ($job) => $job->region === '兵庫県')->merge($jobs->filter(fn ($job) => $job->region !== '兵庫県'))->modelKeys();
    $template = $jobs->first()->only(['company_id', 'title', 'occupation', 'region', 'source_url']);
    foreach ([
        ['source_url' => 'https://example.com/hidden'], ['source_url' => null], ['source_url' => 'not-a-url'],
        ['status' => 'draft'], ['status' => 'paused'], ['status' => 'closed'],
        ['region' => '東京都'], ['occupation' => '電気設計'], ['unavailable_at' => now()],
    ] as $excluded) {
        JobPosting::create(array_replace($template, $excluded));
    }
    // Count the same published projection as the list, never editable authoring data.
    JobPublishedProfile::create(['job_posting_id' => $jobs[0]->id, 'published_at' => now(),
        'profile_data' => ['schema_version' => 1, 'level_one' => $template, 'company' => ['name' => '公開会社']]]);
    $jobs[0]->update(['region' => '東京都', 'occupation' => '電気設計']);
    foreach ([['schema_version' => 99], ['schema_version' => 1, 'level_one' => [...$template, 'source_url' => null]]] as $profile) {
        $excluded = JobPosting::create($template);
        JobPublishedProfile::create(['job_posting_id' => $excluded->id, 'published_at' => now(), 'profile_data' => $profile]);
    }
    $before = $query->getAttributes();
    $service = app(JobDiscoveryService::class);
    foreach ([0, 20, 40] as $offset) {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $withoutMetadata = $service->discover($query, 21, $offset);
        $originalSql = array_map(fn ($entry) => [$entry['query'], $entry['bindings']], DB::getQueryLog());
        DB::flushQueryLog();
        $total = null;
        $withMetadata = $service->discover($query, 21, $offset, $total);
        $metadataSql = array_map(fn ($entry) => [$entry['query'], $entry['bindings']], DB::getQueryLog());
        DB::disableQueryLog();
        expect($total)->toBe(25)->and($metadataSql)->toBe($originalSql)
            ->and($metadataSql)->toHaveCount($offset < 25 ? 3 : 1)
            ->and($withMetadata->toArray())->toBe($withoutMetadata->toArray())
            ->and($withMetadata->modelKeys())->toBe(array_slice($expected, $offset, 21));
        $result = app(JobDecisionUseCaseService::class)->run($query, intdiv($offset, 20) + 1);
        expect($result['total'])->toBe(25)
            ->and(array_column(array_column($result['items'], 'job'), 'id'))->toBe(array_slice(decisionFitOrder($query, [], $expected), $offset, 20))
            ->and(array_keys($result['pagination']))->toBe(['page', 'has_previous', 'has_next']);
    }
    expect($query->getAttributes())->toBe($before);
});

test('empty population has zero display total', function () {
    $query = decisionPageFixture(0);
    $result = app(JobDecisionUseCaseService::class)->run($query);
    expect($result['total'])->toBe(0)->and($result['items'])->toBe([])
        ->and($result['pagination'])->toBe(['page' => 1, 'has_previous' => false, 'has_next' => false]);
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token])
        ->get(decisionPageUrl($query))->assertOk()->assertSee('data-result-total>0</strong>', false);
});

test('compact result cards preserve Fit values detail routes and twenty item fallback across pages', function () {
    $query = decisionPageFixture(25);
    $jobs = JobPosting::orderBy('id')->get();
    Company::whereKey($jobs[0]->company_id)->update(['name' => '関西ものづくり株式会社']);
    foreach ($jobs as $i => $job) {
        $job->update(['title' => ['産業機械の機械設計・製品開発', '生産設備の設計エンジニア', '精密機器の設計・開発担当'][$i % 3].'（'.($i + 1).'）']);
    }
    $expected = decisionFitOrder($query, ['autocad']);
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token]);
    $capture = getenv('JOBDD_UI_CAPTURE_DIR');
    if ($capture && ! is_dir($capture)) {
        mkdir($capture, 0700, true);
    }
    foreach ([1, 2, 3] as $page) {
        $response = $this->get(decisionPageUrl($query, ['page' => $page, 'tools' => ['autocad']]))->assertOk();
        $html = new DOMDocument;
        @$html->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        $dom = new DOMXPath($html);
        $items = $response->viewData('items');
        $cards = $dom->query('//article[@data-job-id]');
        if ($page === 1) {
            $response->assertSee('次の求人を見る（21〜25件）');
        } else {
            $response->assertDontSee('次の求人を見る（');
        }
        expect($response->viewData('total'))->toBe(25)->and($cards->length)->toBe(count($items))
            ->and($dom->query('//article[@hidden]')->length)->toBe(0)
            ->and($dom->query('//*[@data-result-controls or @data-result-window]')->length)->toBe(0)
            ->and($dom->query('//article//details | //article//a[@target="_blank"]')->length)->toBe(0);
        expect($dom->query('//nav[@data-result-pagination]//a[@rel="prev"]')->length)->toBe($page > 1 ? 1 : 0)
            ->and($dom->query('//nav[@data-result-pagination]//a[@rel="next"]')->length)->toBe($page === 1 ? 1 : 0)
            ->and($dom->query('//nav[@data-result-pagination]//*[@aria-disabled="true" and not(@href)]')->length)->toBe(1)
            ->and($dom->query('//nav[@data-result-pagination]//*[@aria-current="page"]')->item(0)->textContent)->toBe($page.'ページ目');
        foreach ($dom->query('//nav[@data-result-pagination]//a') as $link) {
            expect($link->getAttribute('class'))->toContain('jobdd-pagination-button');
            parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $context);
            expect((int) $context['page'])->toBe($link->getAttribute('rel') === 'prev' ? $page - 1 : $page + 1)
                ->and($context['tools'])->toBe(['autocad'])->and($context['sort'])->toBe('fit');
        }
        $actual = [];
        foreach ($cards as $index => $card) {
            $actual[] = (int) $card->getAttribute('data-job-id');
            $fit = $items[$index]['fit'];
            foreach ($fit['axes'] as $axis) {
                expect($dom->query('.//*[@data-axis="'.$axis['key'].'" and @data-status="'.$axis['status'].'"]', $card)->length)->toBe(1);
            }
            foreach (['confirmed_matches' => '確認できた', 'confirmed_mismatches' => '条件と異なる', 'unknowns' => '未確認'] as $key => $label) {
                expect($card->textContent)->toContain($label.' '.$fit['summary'][$key].'項目');
            }
            expect($dom->query('.//input[@name="jobs[]" and @form="compare-selection"]', $card)->length)->toBe(1);
        }
        expect($actual)->toBe(array_slice($expected, ($page - 1) * 20, 20));
        $response->assertDontSee('前の3件を見る')->assertDontSee('次の3件を見る')
            ->assertDontSee('希望職種と掲載職種が一致しています。')->assertDontSee('最終取得日時')
            ->assertDontSee('情報提供元')->assertDontSee('求人元を見る')->assertDontSee('上位3件');
        if ($capture) {
            file_put_contents($capture.'/results-'.$page.'.html', $response->getContent());
        }
    }
    if ($capture) {
        $ids = [$expected[0], $expected[1], $expected[20]];
        $comparison = $this->get(route('query.jobs.compare', ['userQuery' => $query->public_id, 'jobs' => $ids, 'tools' => ['autocad'], 'page' => 2]))->assertOk();
        $detail = $this->get(route('query.jobs.show', ['userQuery' => $query->public_id, 'job' => $expected[0], 'tools' => ['autocad']]))->assertOk();
        file_put_contents($capture.'/comparison.html', $comparison->getContent());
        file_put_contents($capture.'/detail.html', $detail->getContent());
        file_put_contents($capture.'/manifest.json', json_encode(['url' => decisionPageUrl($query, ['tools' => ['autocad']]), 'ids' => $expected, 'compare_ids' => $ids]));
    }
});

test('next page copy describes the actual next page range', function (int $page, string $label) {
    $query = decisionPageFixture(45);
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token])
        ->get(decisionPageUrl($query, ['page' => $page]))->assertOk()
        ->assertSee($label)->assertSee('rel="next"', false)->assertDontSee('data-next-label=', false)
        ->assertDontSee('次の20件を見る')->assertDontSee('他の求人を見る');
})->with([[1, '次の求人を見る（21〜40件）'], [2, '次の求人を見る（41〜45件）']]);
