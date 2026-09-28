<?php

use App\Models\ApplicationRoute;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\UserQuery;
use App\Services\CompanyJobAuthoringData;
use App\Services\CompanyJobPublishService;
use App\Services\CompanyJobReviewService;
use App\Support\JobEyecatchResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

require_once __DIR__.'/../Support/PublishFixture.php';

test('eyecatch migration preserves existing columns and adds only a null URL', function () {
    // A connection-local temporary table shadows the real table; no existing schema is changed.
    DB::statement('CREATE TEMPORARY TABLE job_postings (id BIGINT PRIMARY KEY, title TEXT, status VARCHAR(30), review_status VARCHAR(30))');
    try {
        DB::table('job_postings')->insert(['id' => 1591, 'title' => '既存求人', 'status' => 'published', 'review_status' => 'pending_review']);
        $before = (array) DB::table('job_postings')->first();
        $migration = require database_path('migrations/2026_09_28_000001_add_eyecatch_image_url_to_job_postings.php');
        $migration->up();
        expect((array) DB::table('job_postings')->first())->toBe($before + ['eyecatch_image_url' => null]);
    } finally {
        DB::statement('DROP TEMPORARY TABLE job_postings');
    }
});

test('fallback chooses five categories with specific rules first', function ($title, $occupation, $category) {
    $image = (new JobEyecatchResolver)->present($title, $occupation);
    expect($image['category'])->toBe($category)
        ->and($image['fallback_asset'])->toBe('images/jobdd/eyecatch/'.$category.'.png')
        ->and(is_file(public_path($image['fallback_asset'])))->toBeTrue();
})->with([
    ['部品設計', '機械設計', 'machine'], ['回路設計', null, 'electrical'],
    ['生産技術 設備設計', '機械設計', 'production'], ['PLC 制御設備', '電気設計', 'plc'], ['営業', null, 'generic'],
]);

test('image import requires provider permission and safe HTTP URL', function ($value, $expected) {
    config(['eyecatch.approved_providers' => ['meitec_next']]);
    expect(JobEyecatchResolver::importedUrl('meitec_next', ['eyecatch_image_url' => $value]))->toBe($expected)
        ->and(JobEyecatchResolver::importedUrl('careerjet', ['eyecatch_image_url' => $value]))->toBeNull();
})->with([
    ['https://images.sample.jp/hero.jpg', 'https://images.sample.jp/hero.jpg'],
    ['http://images.sample.jp/hero.jpg', 'http://images.sample.jp/hero.jpg'],
    ['javascript:alert(1)', null], ['data:image/png;base64,abc', null], ['file:///tmp/photo.png', null], ['not a url', null], [[], null], [null, null],
]);

test('backfill dry run and repeat only update null URLs without changing other data', function () {
    config(['eyecatch.approved_providers' => ['meitec_next']]);
    $company = Company::create(['name' => '画像検証会社']);
    $job = JobPosting::create(['company_id' => $company->id, 'title' => '機械設計', 'provider_key' => 'meitec_next', 'external_id' => '123', 'source_url' => 'https://www.m-next.jp/job/123/']);
    $before = $job->fresh()->getAttributes();
    $path = tempnam(sys_get_temp_dir(), 'eyecatch-');
    file_put_contents($path, json_encode(['jobs' => [['external_id' => '123', 'source_url' => $job->source_url, 'eyecatch_image_url' => 'https://images.sample.jp/hero.jpg']]]));
    try {
        $args = ['--input' => $path, '--provider' => 'meitec_next', '--limit' => 1];
        $this->artisan('jobdd:backfill-eyecatch', $args + ['--dry-run' => true])->expectsOutputToContain('"updated":0')->assertSuccessful();
        expect($job->fresh()->getAttributes())->toBe($before);
        $this->artisan('jobdd:backfill-eyecatch', $args)->expectsOutputToContain('"updated":1')->assertSuccessful();
        expect($job->fresh()->getAttributes())->toBe(array_replace($before, ['eyecatch_image_url' => 'https://images.sample.jp/hero.jpg']));
        $this->artisan('jobdd:backfill-eyecatch', $args)->expectsOutputToContain('"updated":0')->assertSuccessful();
        $this->artisan('jobdd:backfill-eyecatch', array_replace($args, ['--limit' => 0]))->assertFailed();
    } finally {
        unlink($path);
    }
});

test('detail hero ordering image safety primary CTA and published image boundary', function () {
    Mail::fake();
    config(['eyecatch.approved_providers' => ['meitec_next']]);
    [$owner, $published, $admin] = publishFixture();
    app(CompanyJobReviewService::class)->request($published, $owner);
    app(CompanyJobPublishService::class)->approve($published, $admin, app(CompanyJobAuthoringData::class)->token($published->fresh()));
    $published->update(['eyecatch_image_url' => 'https://images.sample.jp/PRIVATE-DRAFT.jpg', 'provider_key' => 'meitec_next']);
    $legacy = JobPosting::create(['company_id' => $published->company_id, 'title' => '機械設計エンジニア', 'occupation' => '機械設計', 'region' => '兵庫県', 'salary_min' => 400, 'salary_max' => 650, 'source_url' => 'https://www.m-next.jp/job/123/', 'provider_key' => 'meitec_next', 'eyecatch_image_url' => 'https://images.sample.jp/hero.jpg', 'description' => '装置の機械設計を担当します。']);
    $route = ApplicationRoute::create(['job_posting_id' => $legacy->id, 'route_type' => 'agent', 'provider_key' => 'meitec_next', 'availability_status' => 'available', 'application_url' => $legacy->source_url]);
    $query = UserQuery::create(['public_id' => (string) Str::uuid(), 'session_token' => 'image-test', 'raw_text' => 'fixture', 'occupation' => '機械設計', 'region' => '兵庫県', 'salary_min' => 400]);
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token]);
    foreach (['legacy' => $legacy, 'snapshot' => $published] as $kind => $job) {
        $response = $this->get(route('query.jobs.show', [$query->public_id, $job->id]));
        $response->assertOk()->assertSeeInOrder(['data-job-id=', 'あなたの希望', 'あなたの希望条件との確認', 'この仕事について分かること', '根拠と掲載元', 'この求人への応募方法', '提供元の応募情報を見る', '人材紹介会社へ相談する選択肢'])
            ->assertSee('JobDDイメージ画像')->assertSee('target="_blank" rel="noopener noreferrer"', false)->assertDontSee('PRIVATE-DRAFT');
        if ($kind === 'legacy') {
            $response->assertSee('data-src="https://images.sample.jp/hero.jpg"', false)->assertSee('data-detail-application="'.$route->id.'"', false);
        }
        // Optional export for real-browser verification; fixtures remain in testing transactions.
        if ($directory = getenv('JOBDD_DETAIL_CAPTURE_DIR')) {
            if (! is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
            file_put_contents($directory.'/'.$kind.'.html', $response->getContent());
        }
    }
    foreach (['interaction.route-selected' => 'route_selected', 'interaction.contact-clicked' => 'contact_clicked'] as $name => $event) {
        $this->postJson(route($name), ['user_query_id' => null, 'application_route_id' => $route->id])->assertNoContent();
        expect(DB::table('interaction_logs')->where('event_type', $event)->where('target_id', $route->id)->count())->toBe(1);
    }
    foreach (['null-image' => null, 'unapproved' => 'https://images.sample.jp/hero.jpg'] as $case => $url) {
        $legacy->update(['eyecatch_image_url' => $url, 'provider_key' => $case === 'unapproved' ? 'unapproved' : 'meitec_next']);
        $response = $this->get(route('query.jobs.show', [$query->public_id, $legacy->id]));
        $response->assertOk()->assertDontSee('data-eyecatch-external', false)
            ->assertSee('images/jobdd/eyecatch/machine.png', false)->assertSee('JobDDイメージ画像');
        if ($directory = getenv('JOBDD_DETAIL_CAPTURE_DIR')) {
            file_put_contents($directory.'/'.$case.'.html', $response->getContent());
        }
    }
    $legacy->update(['provider_key' => 'meitec_next', 'eyecatch_image_url' => 'javascript:alert(1)']);
    $this->get(route('query.jobs.show', [$query->public_id, $legacy->id]))->assertOk()->assertDontSee('data-eyecatch-external', false)->assertSee('JobDDイメージ画像');
});

test('bounded backfill resumes past populated rows and production execution is refused', function () {
    config(['eyecatch.approved_providers' => ['meitec_next']]);
    $company = Company::create(['name' => '継続検証会社']);
    $rows = [];
    foreach ([1, 2] as $id) {
        $url = 'https://www.m-next.jp/job/'.$id.'/';
        JobPosting::create(['company_id' => $company->id, 'title' => '機械設計', 'provider_key' => 'meitec_next', 'external_id' => (string) $id, 'source_url' => $url]);
        $rows[] = ['external_id' => (string) $id, 'source_url' => $url, 'eyecatch_image_url' => 'https://images.sample.jp/'.$id.'.jpg'];
    }
    $path = tempnam(sys_get_temp_dir(), 'eyecatch-');
    file_put_contents($path, json_encode(['jobs' => $rows]));
    try {
        $args = ['--input' => $path, '--provider' => 'meitec_next', '--limit' => 1];
        $this->artisan('jobdd:backfill-eyecatch', $args)->expectsOutputToContain('"updated":1')->assertSuccessful();
        expect(JobPosting::whereNotNull('eyecatch_image_url')->count())->toBe(1);
        $this->artisan('jobdd:backfill-eyecatch', $args)->expectsOutputToContain('"updated":1')->assertSuccessful();
        expect(JobPosting::whereNotNull('eyecatch_image_url')->count())->toBe(2);
        $this->app['env'] = 'production';
        $this->artisan('jobdd:backfill-eyecatch', $args)->assertFailed();
    } finally {
        $this->app['env'] = 'testing';
        unlink($path);
    }
});
