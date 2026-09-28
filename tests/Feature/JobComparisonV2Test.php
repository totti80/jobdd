<?php

use App\Models\ApplicationRoute;
use App\Models\Company;
use App\Models\JobFact;
use App\Models\JobPosting;
use App\Models\UserQuery;
use App\Services\CompanyJobAuthoringData;
use App\Services\CompanyJobPublishService;
use App\Services\CompanyJobReviewService;
use App\Services\JobComparisonUseCaseService;
use App\Services\JobSelectionUseCaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

require_once __DIR__.'/../Support/PublishFixture.php';

function compareV2Fixture(int $self, int $legacy): array
{
    Mail::fake();
    $query = UserQuery::create(['public_id' => (string) Str::uuid(), 'session_token' => 'private', 'raw_text' => 'private-input', 'occupation' => '機械設計', 'region' => '兵庫県', 'salary_min' => 400]);
    $jobs = [];
    for ($i = 0; $i < $self; $i++) {
        [$owner, $job, $admin] = publishFixture();
        app(CompanyJobReviewService::class)->request($job, $owner);
        app(CompanyJobPublishService::class)->approve($job, $admin, app(CompanyJobAuthoringData::class)->token($job->fresh()));
        $jobs[] = $job->fresh();
    }
    for ($i = 0; $i < $legacy; $i++) {
        $jobs[] = JobPosting::create(['company_id' => Company::create(['name' => '既存会社'])->id, 'title' => '既存機械設計', 'occupation' => '機械設計', 'region' => '兵庫県', 'source_url' => 'https://careers.legacy-company.jp/jobs/'.$i, 'status' => 'published']);
    }

    return [$query, $jobs];
}

function compareV2Url($query, $jobs): string
{
    return route('query.jobs.compare', ['userQuery' => $query->public_id, 'jobs' => array_map(fn ($job) => $job->id, $jobs)]);
}

test('compare v2 supports published and legacy combinations in selection order without new Fit', function ($self, $legacy) {
    [$query, $jobs] = compareV2Fixture($self, $legacy);
    $jobs = array_reverse($jobs);
    $ids = array_map(fn ($job) => $job->id, $jobs);
    $original = app(JobSelectionUseCaseService::class)->run($query, $ids);
    $response = $this->withSession(['jobdd_query_token_'.$query->public_id => 'private'])->get(compareV2Url($query, $jobs))->assertOk();
    $data = $response->viewData('items');
    expect(array_column(array_column($data, 'fit'), 'job_posting_id'))->toBe($ids);
    expect(array_column($data, 'fit'))->toBe(array_column($original['items'], 'fit'));
    $response->assertDontSee('data-comparison-preferences', false)->assertDontSee('JobDDが事実確認済み')->assertDontSee('おすすめNo.1');
    if ($self) {
        foreach (['生産設備', '構想', '詳細設計', '部品設計', '将来的な担当可能性', 'AutoCAD / 主に使う / 必須経験', '月に数回', 'ほぼ毎日', 'ほとんどない', 'チームで設計', '精度を保つ', '企業提供情報', 'JobDD公開確認済み', '情報源を見る', 'Evidenceを見る', '企業へ直接応募'] as $label) {
            $response->assertSee($label);
        }
        $response->assertDontSee('設計打合せ')->assertDontSee('搬送装置')->assertDontSee('detailed_design');
    }
    if ($legacy) {
        $response->assertSee('外部情報')->assertSee('未確認');
    }
})->with([[2, 0], [1, 1], [0, 2], [2, 1], [3, 0], [0, 3]]);

test('compare snapshot survives unpublished edits and all reads avoid authoring tables', function () {
    [$query, $jobs] = compareV2Fixture(1, 1);
    $ids = array_map(fn ($job) => $job->id, $jobs);
    $jobs[0]->update(['title' => '非公開タイトル', 'review_status' => 'pending_review']);
    $jobs[0]->company()->update(['name' => '非公開会社名']);
    $jobs[0]->structuredProfile()->update(['design_target' => '非公開設計対象']);
    $jobs[0]->toolUsages()->update(['tool_name' => '非公開ツール']);
    DB::enableQueryLog();
    $result = app(JobComparisonUseCaseService::class)->run($query, $ids);
    $sql = array_column(DB::getQueryLog(), 'query');
    DB::disableQueryLog();
    expect(implode(' ', $sql))->not->toContain('job_structured_profiles', 'job_tool_usages', 'job_typical_day_items');
    expect($result['items'][0]['company_name'])->toBe('公開テスト株式会社')
        ->and($result['items'][0]['job']->title)->toBe('機械設計エンジニア')
        ->and($result['items'][0]['comparison']['rows']['design_target']['values'])->toBe(['生産設備']);
    $this->withSession(['jobdd_query_token_'.$query->public_id => 'private'])->get(compareV2Url($query, $jobs))->assertOk()->assertDontSee('非公開');
    $this->get(route('routes.show', $jobs[0]))->assertOk()->assertSee('公開テスト株式会社')->assertDontSee('非公開');
});

test('optional preferences remain display material with unchanged Fit and order', function () {
    [$query, $jobs] = compareV2Fixture(1, 1);
    $ids = array_map(fn ($job) => $job->id, $jobs);
    $before = app(JobComparisonUseCaseService::class)->run($query, $ids);
    $query->update(['detailed_skills' => ['seeker_preferences' => ['design_phases' => ['detailed_design'], 'customer_contact' => 'want_less', 'manufacturing_relation' => 'want_more', 'site_relation' => 'no_preference', 'work_style' => '<script>相談したい</script>']]]);
    $after = app(JobComparisonUseCaseService::class)->run($query->fresh(), $ids);
    expect(array_column($after['items'], 'fit'))->toBe(array_column($before['items'], 'fit'));
    $response = $this->withSession(['jobdd_query_token_'.$query->public_id => 'private'])->get(compareV2Url($query, $jobs))->assertOk()->assertSee('あなたの希望')->assertSee('関わりは少なめがよい')->assertSee('積極的に関わりたい')->assertSee('特に希望なし')->assertSee('<script>相談したい</script>')->assertDontSee('<script>相談したい</script>', false);
    preg_match('/<section[^>]+data-comparison-preferences.*?<\/section>/s', $response->getContent(), $matches);
    expect($matches[0])->not->toContain('MATCH', 'MISMATCH', '条件に一致', '条件と異なる');
});

test('partial snapshots show neutral missing fields and truncate long comparison text', function () {
    [$query, $jobs] = compareV2Fixture(1, 1);
    $snapshot = $jobs[0]->publishedProfile()->first();
    $data = $snapshot->profile_data;
    $data['structured_profile'] = ['work_style' => str_repeat('長い仕事の説明', 100).'末尾は詳細のみ', 'initial_assignment' => null];
    $data['tool_usages'] = [];
    $snapshot->update(['profile_data' => $data]);
    $this->withSession(['jobdd_query_token_'.$query->public_id => 'private'])->get(compareV2Url($query, $jobs))->assertOk()->assertSee('未確認')->assertSee('長い仕事の説明')->assertDontSee('末尾は詳細のみ');
});

test('comparison rejects private and unsupported snapshots without authoring fallback', function ($state) {
    [$query, $jobs] = compareV2Fixture(1, 1);
    if ($state === 'draft') {
        $jobs[0]->update(['status' => 'draft']);
    } else {
        $snapshot = $jobs[0]->publishedProfile()->first();
        $snapshot->update(['profile_data' => [...$snapshot->profile_data, 'schema_version' => 99]]);
    }
    $this->withSession(['jobdd_query_token_'.$query->public_id => 'private'])->get(compareV2Url($query, $jobs))->assertNotFound();
})->with(['draft', 'unsupported']);

test('route summaries use available routes and existing direct agent platform destinations remain accessible', function () {
    [$query, $jobs] = compareV2Fixture(1, 1);
    foreach (['direct', 'agent', 'platform'] as $type) {
        ApplicationRoute::create(['job_posting_id' => $jobs[1]->id, 'route_type' => $type, 'availability_status' => 'available', 'application_url' => 'https://careers.legacy-company.jp/'.$type]);
    }
    ApplicationRoute::create(['job_posting_id' => $jobs[1]->id, 'route_type' => 'platform', 'availability_status' => 'unavailable', 'application_url' => 'https://careers.legacy-company.jp/hidden']);
    $this->withSession(['jobdd_query_token_'.$query->public_id => 'private'])->get(compareV2Url($query, $jobs))->assertOk()->assertSee('企業へ直接応募')->assertSee('人材紹介会社')->assertSee('求人媒体');
    $response = $this->get(route('routes.show', $jobs[1]))->assertOk()->assertDontSee('/hidden');
    foreach (['direct', 'agent', 'platform'] as $type) {
        $response->assertSee('https://careers.legacy-company.jp/'.$type, false);
    }
    expect(DB::table('interaction_logs')->where('event_type', 'route_opened')->where('target_id', $jobs[1]->id)->exists())->toBeTrue();
    $this->get(route('jobs.provenance', $jobs[0]))->assertOk()->assertSee('企業提供情報');
});

test('unsafe external application URLs are not linked while contact events retain their contract', function () {
    [$query, $jobs] = compareV2Fixture(0, 2);
    $route = ApplicationRoute::create(['job_posting_id' => $jobs[0]->id, 'route_type' => 'direct', 'availability_status' => 'available', 'application_url' => 'javascript:alert(1)']);
    $this->get(route('routes.show', $jobs[0]))->assertOk()->assertDontSee('href="javascript:', false);
    foreach (['contact-clicked' => 'contact_clicked', 'route-selected' => 'route_selected'] as $endpoint => $event) {
        $this->postJson(route('interaction.'.$endpoint), ['user_query_id' => null, 'application_route_id' => $route->id])->assertNoContent();
        expect(DB::table('interaction_logs')->where('event_type', $event)->where('target_id', $route->id)->whereNull('user_query_id')->exists())->toBeTrue();
    }
});

test('legacy tool mentions reuse saved Facts without asserting responsibility', function () {
    [$query, $jobs] = compareV2Fixture(0, 2);
    JobFact::create(['job_posting_id' => $jobs[0]->id, 'fact_category' => 'tool', 'fact_key' => 'autocad', 'fact_value' => 'AutoCAD', 'normalized_value' => 'AutoCAD', 'extraction_method' => 'rule', 'verification_status' => 'unverified', 'evidence_text' => '他部署がAutoCADを使用します。']);
    $this->withSession(['jobdd_query_token_'.$query->public_id => 'private'])->get(compareV2Url($query, $jobs))->assertOk()->assertSee('記載：AutoCAD（用途は詳細で確認）')->assertSee('#presence-title', false);
});

test('comparison polish keeps one header selected order rows and detail links', function (int $count) {
    [$query, $jobs] = compareV2Fixture(1, $count - 1);
    $jobs = array_reverse($jobs);
    $url = route('query.jobs.compare', ['userQuery' => $query->public_id, 'jobs' => array_column($jobs, 'id'), 'tools' => ['autocad'], 'page' => 2, 'sort' => 'salary_desc']);
    $response = $this->withSession(['jobdd_query_token_'.$query->public_id => 'private'])->get($url)->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
    $dom = new DOMXPath($document);
    expect($dom->query('//thead')->length)->toBe(1)
        ->and($dom->query('//thead[@class="jobdd-comparison-header"]')->length)->toBe(1)
        ->and($dom->query('//thead/th')->length)->toBe(0)
        ->and($dom->query('//thead/tr/th[@scope="col"]')->length)->toBe($count + 1)
        ->and($dom->query('//dl[contains(@class,"jobdd-condition-summary-compact")]')->length)->toBe(1)
        ->and($dom->query('//*[@data-status-badge="unknown"]')->length)->toBeGreaterThan(0)
        ->and($dom->query('//*[@data-fit-unknown-count]')->length)->toBe($count);
    $headers = $dom->query('//thead/tr/th[@data-job-id]');
    foreach ($headers as $i => $header) {
        expect((int) $header->getAttribute('data-job-id'))->toBe($jobs[$i]->id);
        $link = $dom->query('.//a', $header);
        expect($link->length)->toBe(1)->and($link->item(0)->getAttribute('href'))->toContain('/jobs/'.$jobs[$i]->id, 'sort=salary_desc');
    }
    $response->assertSee('基本情報')->assertSee('仕事の中身（比較材料）')->assertSee('CAD / Tool')
        ->assertSee('未確認')->assertSee('詳細を見る')->assertSee('比較対象を追加・解除する')
        ->assertSee('条件を変更')->assertSee('詳細条件')->assertSee('表示順は選択した順です。');
    if ($directory = getenv('JOBDD_COMPARE_CAPTURE_DIR')) {
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        file_put_contents($directory.'/compare-'.$count.'.html', $response->getContent());
    }
})->with([2, 3]);
