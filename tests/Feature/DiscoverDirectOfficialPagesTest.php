<?php

use App\Models\ApplicationRoute;
use App\Models\Company;
use App\Models\DirectReverseLookupCandidate;
use App\Models\JobPosting;
use App\Models\Source;
use App\Services\DirectLookup\DiscoveryHttpClient;
use App\Services\DirectLookup\OfficialPageDiscovery;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->originalStorage = storage_path();
    $this->discoveryStorage = base_path('storage/framework/testing/discovery-'.Str::uuid());
    app()->useStoragePath($this->discoveryStorage);
    File::ensureDirectoryExists(storage_path('app/private/crawler'));
    Http::preventStrayRequests();
    Sleep::fake();
    $client = Mockery::mock(DiscoveryHttpClient::class)->makePartial();
    $client->shouldReceive('publicAddresses')->andReturn(['93.184.216.34']);
    app()->instance(DiscoveryHttpClient::class, $client);
    $this->freezeTime();
});

afterEach(function () {
    app()->useStoragePath($this->originalStorage);
    File::deleteDirectory($this->discoveryStorage);
});

function discoveryInput(?string $website = 'https://company.example.test/'): array
{
    $company = Company::create(['name' => '探索テスト企業'.Str::uuid(), 'website_url' => $website]);
    $candidate = DirectReverseLookupCandidate::create([
        'company_id' => $company->id, 'region' => '大阪府', 'occupation' => '機械設計',
        'matching_job_count' => 1, 'discovery_source' => 'careerjet', 'direct_status' => 'unverified',
    ]);

    return [
        'company_id' => $company->id, 'company_name' => $company->name, 'candidate_id' => $candidate->id,
        'region' => '大阪府', 'occupation' => '機械設計', 'official_site_url' => $website,
        'official_recruit_url' => null, 'status' => 'unverified',
        'discovery_candidates' => [['candidate_id' => $candidate->id, 'company_id' => $company->id, 'region' => '大阪府', 'occupation' => '機械設計', 'discovery_source' => 'careerjet']],
    ];
}

function runDiscovery($test, array $records, array $options = []): array
{
    File::put(storage_path('app/private/crawler/input.json'), json_encode($records));
    $test->artisan('jobdd:discover-direct-official-pages', array_merge([
        '--input' => storage_path('app/private/crawler/input.json'),
        '--output' => storage_path('app/private/crawler/review.json'),
    ], $options))->assertSuccessful();

    return json_decode(File::get(storage_path('app/private/crawler/review.json')), true, 512, JSON_THROW_ON_ERROR);
}

function discoveryFake(string $home = '<title>会社</title>', string $robots = 'User-agent: *'): void
{
    Http::fake([
        '*/robots.txt' => Http::response($robots, 200, ['Content-Type' => 'text/plain']),
        'https://company.example.test/' => Http::response($home, 200, ['Content-Type' => 'text/html']),
        '*' => Http::response('<title>採用</title>', 200, ['Content-Type' => 'text/html']),
    ]);
}

test('existing URL yields recruitment and ATS candidates with provenance and duplicate URLs removed', function () {
    discoveryFake('<title>企業トップ</title><a href="/recruit?utm_source=a#top">採用</a><a href="/recruit">採用情報</a><a href="https://hrmos.co/pages/test/jobs">募集</a><a href="/terms">利用規約</a>');
    $input = discoveryInput();
    $review = runDiscovery($this, [$input])[0];
    expect($review['discovery_status'])->toBe('discovered')
        ->and($review['review_status'])->toBe('needs_review')
        ->and($review['recruitment_page_candidates'])->toHaveCount(1)
        ->and($review['ats_candidates'])->toHaveCount(1)
        ->and($review['ats_candidates'][0]['url'])->toBe('https://hrmos.co/pages/test/jobs')
        ->and($review['recruitment_page_candidates'][0]['source_url'])->toBe('https://company.example.test/')
        ->and($review['recruitment_page_candidates'][0]['found_via'])->toBe('html_link')
        ->and($review['recruitment_page_candidates'][0]['http_status'])->toBe(200)
        ->and($review['company_website_candidates'][0]['page_title'])->toBe('企業トップ')
        ->and($review['source_candidate_ids'])->toBe([$input['candidate_id']])
        ->and($review['discovery_candidates'])->toBe($input['discovery_candidates'])
        ->and($review['terms_candidates'])->toHaveCount(1);
    Http::assertSent(fn ($request) => $request->hasHeader('User-Agent') && str_contains($request->header('User-Agent')[0], 'JobDDDiscovery'));
});

test('missing URL is not resolved without HTTP or invented URLs', function () {
    Http::fake();
    $review = runDiscovery($this, [discoveryInput(null)])[0];
    expect($review['discovery_status'])->toBe('not_resolved')
        ->and($review['company_website_candidates'])->toBe([])
        ->and($review['official_site_url'])->toBeNull();
    Http::assertNothingSent();
});

test('HTTP failure produces crawl_failed', function () {
    Http::fake(['*/robots.txt' => Http::response('', 404), '*' => Http::response('', 500)]);
    $review = runDiscovery($this, [discoveryInput()])[0];
    expect($review['discovery_status'])->toBe('crawl_failed')
        ->and($review['fetch_attempts'][0]['http_status'])->toBe(500);
    Http::assertSentCount(2);
});

test('robots restrictions fail closed without fetching the page', function (string $robots) {
    discoveryFake('<title>会社</title>', $robots);
    $review = runDiscovery($this, [discoveryInput()])[0];
    expect($review['discovery_status'])->toBe('blocked');
    Http::assertSentCount(1);
})->with(['User-agent: *'."\nDisallow: /", 'User-agent: *'."\nCrawl-delay: 10", 'User-agent: OtherBot'."\nDisallow: /*$"]);

test('robots request errors are blocked', function () {
    Http::fake(['*' => Http::response('', 503)]);
    expect(runDiscovery($this, [discoveryInput()])[0]['discovery_status'])->toBe('blocked');
    Http::assertSentCount(1);
});

test('redirect final URL is recorded and target robots are checked', function () {
    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        'https://company.example.test/' => Http::response('', 302, ['Location' => 'https://new.example.test/recruit']),
        'https://new.example.test/recruit' => Http::response('<title>採用情報</title>', 200, ['Content-Type' => 'text/html']),
    ]);
    $review = runDiscovery($this, [discoveryInput()])[0];
    expect($review['recruitment_page_candidates'][0]['url'])->toBe('https://new.example.test/recruit')
        ->and($review['fetch_attempts'][0]['final_url'])->toBe('https://new.example.test/recruit');
    Http::assertSent(fn ($request) => $request->url() === 'https://new.example.test/robots.txt');
});

test('review output remains unverified and processor cannot store it as evidence', function () {
    discoveryFake();
    $record = discoveryInput();
    $before = DirectReverseLookupCandidate::all()->toArray();
    $review = runDiscovery($this, [$record])[0];
    $this->artisan('jobdd:process-direct-reverse-lookup-queue', [
        '--input' => str_replace(base_path().'/', '', storage_path('app/private/crawler/review.json')),
    ])->assertSuccessful();
    expect($review['status'])->toBe('unverified')
        ->and($review['official_site_url'])->toBeNull()
        ->and($review['official_recruit_url'])->toBeNull()
        ->and(DirectReverseLookupCandidate::all()->toArray())->toBe($before)
        ->and(JobPosting::count())->toBe(0)->and(ApplicationRoute::count())->toBe(0)->and(Source::count())->toBe(0);
});

test('limit and repeated input are idempotent snapshots', function () {
    discoveryFake();
    $records = [discoveryInput(), discoveryInput()];
    $first = runDiscovery($this, $records, ['--limit' => 1]);
    $second = runDiscovery($this, $records, ['--limit' => 1]);
    expect($first)->toHaveCount(1)->and($second)->toBe($first);
});

test('queue builder output is accepted and all company cells are preserved', function () {
    discoveryFake();
    $record = discoveryInput();
    DirectReverseLookupCandidate::create([
        'company_id' => $record['company_id'], 'region' => '兵庫県', 'occupation' => '電気設計',
        'matching_job_count' => 1, 'discovery_source' => 'recruit_agent', 'direct_status' => 'unverified',
    ]);
    $this->artisan('jobdd:build-direct-lookup-queue')->assertSuccessful();
    $this->artisan('jobdd:discover-direct-official-pages', [
        '--input' => storage_path('app/private/crawler/direct_lookup_queue.json'),
        '--output' => storage_path('app/private/crawler/review.json'),
    ])->assertSuccessful();
    $review = json_decode(File::get(storage_path('app/private/crawler/review.json')), true)[0];
    expect($review['discovery_candidates'])->toHaveCount(2)->and($review['source_candidate_ids'])->toHaveCount(2);
});

test('unsafe paths and bulk limits are rejected without HTTP', function () {
    Http::fake();
    $this->artisan('jobdd:discover-direct-official-pages', ['--limit' => 6])->assertFailed();
    $this->artisan('jobdd:discover-direct-official-pages', ['--output' => 'public/review.json'])->assertFailed();
    Http::assertNothingSent();
});

test('URL normalization preserves distinct jobs and rejects authentication media and known discovery providers', function () {
    expect(OfficialPageDiscovery::normalizeUrl('/jobs?id=1&utm_source=x#top', 'https://company.example.test/'))->toBe('https://company.example.test/jobs?id=1')
        ->and(OfficialPageDiscovery::normalizeUrl('/jobs?id=2', 'https://company.example.test/'))->toBe('https://company.example.test/jobs?id=2');
    foreach (['https://127.0.0.1/', 'http://localhost/', 'https://company.example.test/login', 'https://company.example.test/a.pdf', 'https://www.r-agent.com/jobs', 'https://x:password@company.example.test/', 'file:///etc/passwd'] as $url) {
        expect(OfficialPageDiscovery::normalizeUrl($url))->toBeNull();
    }
});

test('non public DNS targets are blocked before any request', function () {
    Http::fake();
    $client = Mockery::mock(DiscoveryHttpClient::class)->makePartial();
    $client->shouldReceive('publicAddresses')->andReturn([]);
    expect($client->fetch('https://company.example.test/')['state'])->toBe('blocked');
    Http::assertNothingSent();
});

test('page and candidate budgets bound query expansion', function () {
    $links = '';
    for ($i = 0; $i < 100; $i++) {
        $links .= '<a href="/jobs?id='.$i.'">求人</a>';
    }
    discoveryFake('<title>会社</title>'.$links);
    $review = runDiscovery($this, [discoveryInput()])[0];
    expect($review['fetch_attempts'])->toHaveCount(5)
        ->and($review['job_page_candidates'])->toHaveCount(29);
    Http::assertSentCount(6);
});

test('redirect cannot reach private addresses or a robots denied target', function () {
    Http::fake([
        'https://company.example.test/robots.txt' => Http::response('', 404),
        'https://company.example.test/' => Http::response('', 302, ['Location' => 'https://target.example.test/recruit']),
        'https://target.example.test/robots.txt' => Http::response("User-agent: *\nDisallow: /", 200, ['Content-Type' => 'text/plain']),
    ]);
    $review = runDiscovery($this, [discoveryInput()])[0];
    expect($review['discovery_status'])->toBe('blocked');
    Http::assertSentCount(3);
    Http::assertNotSent(fn ($request) => $request->url() === 'https://target.example.test/recruit');
});

test('non HTML responses are skipped', function () {
    Http::fake(['*/robots.txt' => Http::response('', 404), '*' => Http::response('binary', 200, ['Content-Type' => 'application/pdf'])]);
    $review = runDiscovery($this, [discoveryInput()])[0];
    expect($review['discovery_status'])->toBe('blocked')
        ->and($review['fetch_attempts'][0]['reason'])->toBe('non_html');
});

test('maximum depth stops recursive recruitment links', function () {
    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        'https://company.example.test/' => Http::response('<a href="/recruit/1">採用</a>', 200, ['Content-Type' => 'text/html']),
        'https://company.example.test/recruit/1' => Http::response('<a href="/recruit/2">採用</a>', 200, ['Content-Type' => 'text/html']),
        'https://company.example.test/recruit/2' => Http::response('<a href="/recruit/3">採用</a>', 200, ['Content-Type' => 'text/html']),
    ]);
    $review = runDiscovery($this, [discoveryInput()])[0];
    expect($review['fetch_attempts'])->toHaveCount(3);
    Http::assertSentCount(4);
});

test('connection failure is recorded without retrying the page', function () {
    Http::fake(['*/robots.txt' => Http::response('', 404), '*' => Http::failedConnection()]);
    $review = runDiscovery($this, [discoveryInput()])[0];
    expect($review['discovery_status'])->toBe('crawl_failed')
        ->and($review['fetch_attempts'][0]['reason'])->toBe('request_failed');
});
