<?php

use App\Models\ApplicationRoute;
use App\Models\Company;
use App\Models\JobFact;
use App\Models\JobPosting;
use App\Models\UserQuery;
use App\Services\JobFitService;
use App\Support\AnonymousCompany;
use Illuminate\Support\Str;

function uiV5Dom($response, string $capture = ''): DOMXPath
{
    $html = $response->assertOk()->getContent();
    if ($capture !== '' && getenv('JOBDD_UI_CAPTURE_DIR')) {
        $directory = getenv('JOBDD_UI_CAPTURE_DIR');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        file_put_contents($directory.'/'.$capture.'.html', $html);
    }
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);

    return new DOMXPath($dom);
}

function uiV5Fixture(): array
{
    $query = UserQuery::create(['public_id' => (string) Str::uuid(), 'session_token' => 'ui-v5-token',
        'raw_text' => 'private-input', 'occupation' => '機械設計', 'region' => '兵庫県', 'salary_min' => 600]);
    $company = Company::create(['name' => '表示検証会社']);
    $jobs = collect();
    foreach (range(1, 4) as $number) {
        $jobs->push(JobPosting::create(['company_id' => $company->id,
            'title' => str_repeat('長い求人タイトル・機械設計 ', 3).$number,
            'occupation' => '機械設計', 'region' => '兵庫県', 'salary_min' => 400, 'salary_max' => 500,
            'source_url' => 'https://careers.ui-v5.jp/jobs/'.$number, 'provider_key' => 'saved-provider',
            'last_seen_at' => '2026-09-19 00:00:00', 'published_at' => '2026-09-01 00:00:00',
            'description' => '【他部署】他部署ではAutoCADを使用しています。']));
    }
    JobFact::create(['job_posting_id' => $jobs[0]->id, 'fact_category' => 'tool', 'fact_key' => 'autocad',
        'fact_value' => 'AutoCAD', 'normalized_value' => 'AutoCAD', 'extraction_method' => 'rule',
        'verification_status' => 'verified', 'observed_at' => '2026-09-19 00:00:00',
        'evidence_text' => '他部署ではAutoCADを使用しています。']);

    return [$query, $jobs];
}

test('graduation entry integrates scope hero responsive form and explanation with one main heading', function () {
    $response = $this->get(route('jobs.start'));
    $dom = uiV5Dom($response, 'graduation-start');
    expect($dom->query('//header')->length)->toBe(1)
        ->and($dom->query('//h1')->length)->toBe(1)
        ->and($dom->query('//*[@data-entry-layout]/form')->length)->toBe(1)
        ->and($dom->query('//*[@data-entry-layout]/aside')->length)->toBe(1)
        ->and($dom->query('//textarea[@id="custom_tools" and @name="custom_tools" and @maxlength="500"]')->length)->toBe(1)
        ->and($dom->query('//label[@for="custom_tools"]')->length)->toBe(1);
    $response->assertSee('求人を探すだけでは、わからない。')->assertSee('仕事の中身まで比べて、選ぶ。')
        ->assertSee('近畿6府県 × 機械設計・電気設計 専門')->assertSee('卒業制作版')
        ->assertSee('JobDDで分かること')->assertSee('根拠を確認')->assertSee('2〜3求人')
        ->assertSee('grid-cols-1')->assertSee('lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]', false)
        ->assertSeeInOrder(['<header', '近畿6府県 ×', 'id="entry-title"', '<form', '<aside'])
        ->assertDontSee('TOP3')->assertDontSee('おすすめ')->assertDontSee('全国対応');
});

test('visual polish groups tools keeps a direct form link and makes icons decorative', function () {
    $response = $this->get(route('jobs.start'));
    $dom = uiV5Dom($response, 'visual-start');
    expect($dom->query('//header//*[@data-jobdd-brand]')->length)->toBe(1)
        ->and($dom->query('//header//*[@data-jobdd-brand]//img[@alt="JobDD" and @width="1448" and @height="1086"]')->length)->toBe(1)
        ->and($dom->query('//a[@href="#entry-form"]')->length)->toBe(1)
        ->and($dom->query('//form[@id="entry-form" and @method="POST"]')->length)->toBe(1)
        ->and($dom->query('//*[@data-tool-group]//input[@name="tools[]"]')->length)->toBe(7)
        ->and($dom->query('//*[@data-tool-group]//textarea[@name="custom_tools"]')->length)->toBe(1)
        ->and($dom->query('//svg[not(@aria-hidden="true") or not(@focusable="false")]')->length)->toBe(0)
        ->and($dom->query('//img')->length)->toBe(2)
        ->and($dom->query('//img[contains(@src,"jobdd-hero-kinki.png") and @width="1672" and @height="941" and @fetchpriority="high" and not(@loading="lazy")]')->length)->toBe(1);
    foreach ($dom->query('//img') as $image) {
        expect(trim($image->getAttribute('alt')))->not->toBe('');
        expect(is_file(public_path('images/jobdd/'.basename($image->getAttribute('src')))))->toBeTrue();
    }
    $response->assertSee('地図で見る')->assertSee('実際の勤務地を示すものではありません。')
        ->assertSee('確認できた')->assertSee('条件と異なる')->assertSee('未確認')
        ->assertSee('判定未対応')->assertSee('最終判断は、あなた自身で');
    $response->assertSee('根拠とともに、仕事を選ぶ。')->assertSee('近畿の機械・電気設計')
        ->assertDontSee('根拠とともに、求人を比較');
    expect($dom->query('//*[contains(@class,"jobdd-hero-frame")]/img')->length)->toBe(1)
        ->and($dom->query('//aside//*[@data-explanation]')->length)->toBe(6);
    foreach (['confirmed', 'different', 'unknown', 'evidence', 'compare', 'map'] as $item) {
        expect($dom->query('//aside//*[@data-explanation="'.$item.'"]//h3')->length)->toBe(1)
            ->and($dom->query('//aside//*[@data-explanation="'.$item.'"]//svg[@aria-hidden="true"]')->length)->toBe(1);
    }
});

test('v5 form has native labelled choices and field errors preserve safe values', function () {
    $dom = uiV5Dom($this->get(route('jobs.start')), 'start');
    expect($dom->query('//input[@type="radio" and @name="occupation"]')->length)->toBe(2)
        ->and($dom->query('//input[@type="checkbox" and @name="tools[]"]')->length)->toBe(7)
        ->and($dom->query('//select[@name="region" and @multiple]')->length)->toBe(0)
        ->and($dom->query('//button[@type="submit"]')->item(0)->textContent)->toBe('求人候補を見る');
    foreach ($dom->query('//input[@type="radio" or @type="checkbox"]') as $control) {
        expect($dom->query('//label[@for="'.$control->getAttribute('id').'"]')->length)->toBe(1);
    }
    $response = $this->post(route('jobs.store'), ['occupation' => '機械設計', 'region' => '大阪府',
        'salary_min' => -1, 'tools' => ['autocad', 'autocad']])->assertStatus(422);
    $html = new DOMDocument;
    @$html->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
    $errors = new DOMXPath($html);
    expect($errors->query('//*[@id="salary_min" and @aria-invalid="true"]')->length)->toBe(1)
        ->and($errors->query('//*[@id="start-tools-autocad" and @checked and @aria-invalid="true"]')->length)->toBe(1)
        ->and($errors->query('//input[@name="occupation" and @checked and @value="機械設計"]')->length)->toBe(1)
        ->and($errors->query('//option[@selected and @value="大阪府"]')->length)->toBe(1);
    foreach ($errors->query('//*[@aria-describedby]') as $node) {
        foreach (explode(' ', $node->getAttribute('aria-describedby')) as $id) {
            expect($errors->query('//*[@id="'.$id.'"]')->length)->toBe(1);
        }
    }
    $this->assertDatabaseCount('user_queries', 0);
});

test('v5 list keeps canonical checkboxes and every axis outside closed evidence', function () {
    [$query, $jobs] = uiV5Fixture();
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token]);
    $response = $this->get(route('query.jobs', ['userQuery' => $query->public_id, 'tools' => ['autocad']]));
    $dom = uiV5Dom($response, 'list');
    expect($dom->query('//input[@name="jobs[]"]')->length)->toBe(4)
        ->and($dom->query('//input[@name="jobs[]" and @form="compare-selection"]')->length)->toBe(4)
        ->and($dom->query('//form[@id="compare-selection" and @method="GET"]')->length)->toBe(1)
        ->and($dom->query('//article//*[@data-axis and not(ancestor::details)]')->length)->toBe(16)
        ->and($dom->query('//details[@open]')->length)->toBe(0);
    foreach ($dom->query('//article') as $card) {
        expect($dom->query('.//*[@data-status="match"]', $card)->length)->toBeGreaterThan(0)
            ->and($dom->query('.//*[@data-status="mismatch"]', $card)->length)->toBeGreaterThan(0)
            ->and($dom->query('.//*[@data-status="unknown"]', $card)->length)->toBeGreaterThan(0);
    }
    expect($dom->query('//*[@data-jobdd-root]//progress | //*[@data-jobdd-root]//*[@role="progressbar"]')->length)->toBe(0);
    foreach (['一致率', 'TOP3', 'score', 'おすすめ'] as $forbidden) {
        $response->assertDontSee($forbidden);
    }
    $response->assertSee('最終取得日時')->assertSee('この求人の掲載元')->assertSee('合わないという意味ではありません。');
});

test('v5 comparison has row headers and equal identifiable columns for two or three jobs', function ($count) {
    [$query, $jobs] = uiV5Fixture();
    $ids = $jobs->take($count)->reverse()->pluck('id')->all();
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token]);
    $response = $this->get(route('query.jobs.compare', ['userQuery' => $query->public_id, 'jobs' => $ids, 'tools' => ['autocad']]));
    $dom = uiV5Dom($response, 'compare-'.$count);
    expect($dom->query('//thead//th[@scope="col"]')->length)->toBe($count + 1)
        ->and($dom->query('//th[@scope="row"]')->length)->toBe(21)
        ->and($dom->query('//tbody')->length)->toBe(4)
        ->and($dom->query('//caption')->length)->toBe(1);
    $actual = [];
    foreach ($dom->query('//thead//th[@data-job-id]') as $node) {
        $actual[] = (int) $node->getAttribute('data-job-id');
    }
    expect($actual)->toBe($ids);
    $response->assertSee('左右にスクロールできます')->assertSee('詳細を見る')->assertDontSee('winner');
})->with([2, 3]);

test('v5 detail separates presence evidence and stored route availability without promoting unknown', function () {
    [$query, $jobs] = uiV5Fixture();
    foreach (['available', 'unknown', 'unavailable'] as $status) {
        ApplicationRoute::create(['job_posting_id' => $jobs[0]->id, 'route_type' => 'agent',
            'provider_key' => $status, 'availability_status' => $status,
            'application_url' => 'https://apply.ui-v5.jp/'.$status, 'notes' => str_repeat('保存された長い補足。', 60)]);
    }
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token]);
    $response = $this->get(route('query.jobs.show', ['userQuery' => $query->public_id, 'job' => $jobs[0]->id, 'tools' => ['autocad']]));
    $dom = uiV5Dom($response, 'detail');
    expect($dom->query('//*[@data-application-route]//a')->length)->toBe(1)
        ->and($dom->query('//*[@data-application-route]//details')->length)->toBe(3)
        ->and($dom->query('//*[@id="presence-title"]/following-sibling::*//details')->length)->toBeGreaterThan(0);
    $response->assertSee('他部署の業務として記載')->assertSee('記録上の検証状態')
        ->assertSee('利用可能として記録')->assertSee('利用状況未確認')->assertSee('利用不可として記録')
        ->assertSeeInOrder(['希望条件との確認結果', '求人本文で確認できた技術・工程', '根拠と求人元の情報', 'この求人で確認できた応募方法'])
        ->assertDontSee('href="https://apply.ui-v5.jp/unknown"', false)
        ->assertDontSee('href="https://apply.ui-v5.jp/unavailable"', false);
});

test('anonymous company remains explicit across list detail comparison and map without changing fit', function () {
    [$query, $jobs] = uiV5Fixture();
    $job = $jobs[0];
    $fit = app(JobFitService::class);
    $before = $fit->evaluate($query, $job, []);
    Company::whereKey($job->company_id)->update(['name' => AnonymousCompany::NAME]);
    expect($fit->evaluate($query, $job->fresh(), []))->toBe($before);
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token]);
    foreach ([
        route('query.jobs', ['userQuery' => $query->public_id]),
        route('query.jobs', ['userQuery' => $query->public_id, 'view' => 'map']),
        route('query.jobs.show', ['userQuery' => $query->public_id, 'job' => $job->id]),
        route('query.jobs.compare', ['userQuery' => $query->public_id, 'jobs' => $jobs->take(2)->pluck('id')->all()]),
    ] as $url) {
        $this->get($url)->assertOk()->assertSee(AnonymousCompany::LABEL)
            ->assertSee(AnonymousCompany::NOTE);
    }
});
