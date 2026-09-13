<?php

use App\Models\Company;
use App\Models\DirectReverseLookupCandidate;
use App\Models\JobPosting;
use App\Models\Source;
use App\Services\DirectLookup\CompanyWebsiteSearchProviderInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->previousSearchStorage = storage_path();
    $this->searchStorage = base_path('storage/framework/testing/search-'.Str::uuid());
    app()->useStoragePath($this->searchStorage);
    File::ensureDirectoryExists(storage_path('app/private/crawler'));
    Http::preventStrayRequests();
    Http::fake();
    $this->freezeTime();
});

afterEach(function () {
    Http::assertNothingSent();
    app()->useStoragePath($this->previousSearchStorage);
    File::deleteDirectory($this->searchStorage);
});

function searchCompany(?string $website = null): Company
{
    $company = Company::create(['name' => '検索企業'.Str::uuid(), 'website_url' => $website]);
    DirectReverseLookupCandidate::create(['company_id' => $company->id, 'region' => '大阪府', 'occupation' => '機械設計', 'discovery_source' => 'recruit_agent', 'matching_job_count' => 1, 'direct_status' => 'unverified']);

    return $company;
}

function runSearchResolver($test, int $limit = 5): array
{
    $test->artisan('jobdd:build-direct-lookup-queue')->assertSuccessful();
    $test->artisan('jobdd:resolve-direct-company-websites', [
        '--input' => storage_path('app/private/crawler/direct_lookup_queue.json'),
        '--output' => storage_path('app/private/crawler/direct_company_website_review.json'), '--limit' => $limit,
    ])->assertSuccessful();

    return json_decode(File::get(storage_path('app/private/crawler/direct_company_website_review.json')), true, 512, JSON_THROW_ON_ERROR);
}

function fakeCompanySearch(array $results = [], string $status = 'searched', int $calls = 1)
{
    $provider = Mockery::mock(CompanyWebsiteSearchProviderInterface::class);
    $provider->shouldReceive('search')->times($calls)->andReturnUsing(fn ($companyName) => [
        'provider' => 'test_provider', 'query' => $companyName.' 公式', 'searched_at' => now()->toIso8601String(),
        'results' => $results, 'api_calls' => 0, 'status' => $status,
        'reason' => $status === 'searched' ? 'fake_results' : 'fake_search_failure',
    ]);
    app()->instance(CompanyWebsiteSearchProviderInterface::class, $provider);

    return $provider;
}

test('existing DB seed never invokes search provider', function () {
    searchCompany('https://company.example.test/');
    fakeCompanySearch(calls: 0);
    expect(runSearchResolver($this)[0]['search']['status'])->toBe('not_needed');
});

test('stored source seed skips search and only unresolved company falls back', function () {
    $known = searchCompany();
    Source::create(['publisher' => $known->name, 'source_type' => 'official_site', 'url' => 'https://known.example.test/']);
    $unknown = searchCompany();
    $provider = fakeCompanySearch([['url' => 'https://result.example.test/', 'title' => $unknown->name, 'snippet' => $unknown->name.'公式', 'rank' => 1]]);
    $rows = runSearchResolver($this);
    expect($rows[0]['search']['status'])->toBe('not_needed')->and($rows[1]['search']['status'])->toBe('searched');
    $provider->shouldHaveReceived('search')->with($unknown->name)->once();
});

test('name matches rank first and media are excluded without automatic confirmation', function () {
    $company = searchCompany();
    fakeCompanySearch([
        ['url' => 'https://www.indeed.com/', 'title' => $company->name, 'snippet' => '', 'rank' => 1],
        ['url' => 'https://en.wikipedia.org/wiki/Company', 'title' => $company->name, 'snippet' => '', 'rank' => 2],
        ['url' => 'https://unmatched.example.test/', 'title' => 'Other company', 'snippet' => '', 'rank' => 3],
        ['url' => 'https://company.example.test/', 'title' => $company->name, 'snippet' => $company->name.'公式', 'rank' => 4],
        ['url' => 'https://company.example.test/?utm_source=x#top', 'title' => $company->name, 'snippet' => '', 'rank' => 5],
    ]);
    $row = runSearchResolver($this)[0];
    expect($row['website_candidates'])->toHaveCount(2)
        ->and($row['website_candidates'][0]['url'])->toBe('https://company.example.test/')
        ->and($row['website_candidates'][0]['rank'])->toBe(4)
        ->and($row['website_candidates'][0]['candidate_score'])->toBe(3)
        ->and($row['website_candidates'][0]['review_status'])->toBe('needs_review')
        ->and($row['website_candidates'][0]['provider'])->toBe('test_provider')
        ->and($row['status'])->toBe('unverified');
    expect($company->fresh()->website_url)->toBeNull()
        ->and(DirectReverseLookupCandidate::sole()->direct_status)->toBe('unverified')
        ->and(JobPosting::count())->toBe(0);
});

test('empty result never invents a URL', function () {
    searchCompany();
    fakeCompanySearch();
    $row = runSearchResolver($this)[0];
    expect($row['resolution_status'])->toBe('not_resolved')->and($row['website_candidates'])->toBe([]);
});

test('same fake search produces idempotent review JSON', function () {
    $company = searchCompany();
    fakeCompanySearch([['url' => 'https://company.example.test/about', 'title' => $company->name, 'snippet' => '公式', 'rank' => 1]], calls: 2);
    expect(runSearchResolver($this))->toBe(runSearchResolver($this));
});

test('limit bounds provider calls and review is processor compatible', function () {
    searchCompany();
    searchCompany();
    fakeCompanySearch();
    expect(runSearchResolver($this, 1))->toHaveCount(1);
    $this->artisan('jobdd:process-direct-reverse-lookup-queue', [
        '--input' => str_replace(base_path().'/', '', storage_path('app/private/crawler/direct_company_website_review.json')),
    ])->assertSuccessful();
    expect(Source::count())->toBe(0);
});

test('provider failure remains search_failed with no guessed URLs', function () {
    searchCompany();
    fakeCompanySearch(status: 'search_failed');
    $row = runSearchResolver($this)[0];
    expect($row['resolution_status'])->toBe('search_failed')->and($row['website_candidates'])->toBe([]);
});

test('default provider safely skips without any API key or external request', function () {
    searchCompany();
    $row = runSearchResolver($this)[0];
    expect($row['resolution_status'])->toBe('not_resolved')->and($row['search']['status'])->toBe('skipped')
        ->and($row['search']['reason'])->toBe('free_search_provider_not_configured')
        ->and($row['search']['api_calls'])->toBe(0);
});

test('provider exceptions are sanitized and never expose credentials', function () {
    searchCompany();
    $provider = Mockery::mock(CompanyWebsiteSearchProviderInterface::class);
    $provider->shouldReceive('search')->once()->andThrow(new RuntimeException('secret-test-token'));
    app()->instance(CompanyWebsiteSearchProviderInterface::class, $provider);
    $row = runSearchResolver($this)[0];
    expect($row['resolution_status'])->toBe('search_failed')
        ->and(json_encode($row))->not->toContain('secret-test-token')
        ->and($row['search']['api_calls'])->toBeNull();
});

test('only first five provider results are considered', function () {
    searchCompany();
    fakeCompanySearch(array_map(fn ($rank) => [
        'url' => 'https://company'.$rank.'.example.test/', 'title' => 'company', 'snippet' => '', 'rank' => $rank,
    ], range(1, 6)));
    expect(runSearchResolver($this)[0]['website_candidates'])->toHaveCount(5);
});
