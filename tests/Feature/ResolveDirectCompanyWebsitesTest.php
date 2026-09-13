<?php

use App\Models\Agency;
use App\Models\ApplicationRoute;
use App\Models\Company;
use App\Models\DirectReverseLookupCandidate;
use App\Models\JobPosting;
use App\Models\Source;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->oldSeedStorage = storage_path();
    $this->seedStorage = base_path('storage/framework/testing/seeds-'.Str::uuid());
    app()->useStoragePath($this->seedStorage);
    File::ensureDirectoryExists(storage_path('app/private/crawler'));
    Http::preventStrayRequests();
    Http::fake();
    $this->freezeTime();
});

afterEach(function () {
    Http::assertNothingSent();
    app()->useStoragePath($this->oldSeedStorage);
    File::deleteDirectory($this->seedStorage);
});

function seedCompany(?string $url = null): Company
{
    $company = Company::create(['name' => 'Seed企業'.Str::uuid(), 'website_url' => $url]);
    DirectReverseLookupCandidate::create([
        'company_id' => $company->id, 'region' => '大阪府', 'occupation' => '機械設計',
        'discovery_source' => 'recruit_agent', 'matching_job_count' => 1, 'direct_status' => 'unverified',
    ]);

    return $company;
}

function seedRun($test, array $options = []): array
{
    $test->artisan('jobdd:build-direct-lookup-queue')->assertSuccessful();
    $test->artisan('jobdd:resolve-direct-company-websites', array_merge([
        '--input' => storage_path('app/private/crawler/direct_lookup_queue.json'),
        '--output' => storage_path('app/private/crawler/direct_company_website_review.json'),
    ], $options))->assertSuccessful();

    return json_decode(File::get(storage_path('app/private/crawler/direct_company_website_review.json')), true, 512, JSON_THROW_ON_ERROR);
}

function seedJob(Company $company, string $description): JobPosting
{
    return JobPosting::create(['company_id' => $company->id, 'title' => '機械設計担当', 'source_url' => 'https://agent.example.test/job/1', 'description' => $description]);
}

function seedSnapshot(array $rows, string $file = 'recruit_agent_jobs.json'): void
{
    File::put(storage_path('app/private/crawler/'.$file), json_encode(['jobs' => $rows], JSON_UNESCAPED_UNICODE));
}

test('stored company URL is emitted with database evidence and no confirmed update', function () {
    $company = seedCompany('https://employer.example.test/');
    $before = $company->fresh()->toArray();
    $review = seedRun($this)[0];
    expect($review['status'])->toBe('unverified')->and($review['review_status'])->toBe('needs_review')
        ->and($review['website_candidates'][0]['url'])->toBe('https://employer.example.test/')
        ->and($review['website_candidates'][0]['source_type'])->toBe('company_record')
        ->and($review['website_candidates'][0]['evidence_locator'])->toBe('companies:'.$company->id)
        ->and($review['source_candidate_ids'])->toHaveCount(1)
        ->and($company->fresh()->toArray())->toBe($before)
        ->and(DirectReverseLookupCandidate::sole()->direct_status)->toBe('unverified');
});

test('JSON LD employer URL and sameAs require exact organization identity', function () {
    $company = seedCompany();
    $data = ['@type' => 'JobPosting', 'hiringOrganization' => ['@type' => 'Organization', 'name' => $company->name, 'url' => 'https://employer.example.test/', 'sameAs' => ['https://corporate.example.test/', 'https://www.linkedin.com/company/test']]];
    seedJob($company, '<script type="application/ld+json">'.json_encode($data).'</script>');
    $review = seedRun($this)[0];
    expect(array_column($review['website_candidates'], 'url'))->toBe(['https://employer.example.test/', 'https://corporate.example.test/'])
        ->and($review['website_candidates'][0]['found_via'])->toBe('organization_identity');
});

test('unrelated organization and generic job URL or canonical are not employer evidence', function () {
    $company = seedCompany();
    seedJob($company, '<link rel="canonical" href="https://portal.example.test/job/1"><script type="application/ld+json">'.json_encode([
        '@type' => 'Organization', 'name' => '別会社', 'url' => 'https://unrelated.example.test/',
    ]).'</script>');
    expect(seedRun($this)[0]['resolution_status'])->toBe('not_resolved');
});

test('official Source URL is reused only for matching publisher', function () {
    $company = seedCompany();
    Source::create(['publisher' => $company->name, 'source_type' => 'official_site', 'url' => 'https://employer.example.test/']);
    Source::create(['publisher' => '別会社', 'source_type' => 'official_site', 'url' => 'https://other.example.test/']);
    $review = seedRun($this)[0];
    expect($review['website_candidates'])->toHaveCount(1)->and($review['website_candidates'][0]['source_type'])->toBe('official_source');
});

test('stored Source HTML is bound by source URL and yields explicit corporate link', function () {
    $company = seedCompany();
    $job = seedJob($company, '仕事内容');
    Source::create(['source_type' => 'agent_job', 'url' => $job->source_url]);
    seedSnapshot([['source_url' => $job->source_url, 'source_html' => '<a href="https://employer.example.test/">企業公式サイト</a><a href="https://unrelated.example.test/">広告</a>']]);
    $review = seedRun($this)[0];
    expect($review['website_candidates'])->toHaveCount(1)
        ->and($review['website_candidates'][0]['source_type'])->toBe('source_html')
        ->and($review['website_candidates'][0]['found_via'])->toBe('explicit_corporate_link')
        ->and($review['website_candidates'][0]['evidence_excerpt'])->toContain('企業公式サイト');
});

test('saved ATS corporate links are candidates but ATS itself is not a company top', function () {
    $company = seedCompany();
    Source::create(['publisher' => $company->name, 'source_type' => 'official_recruiting', 'url' => 'https://hrmos.co/pages/test/jobs/1']);
    seedSnapshot([['company_name' => $company->name, 'source_url' => 'https://hrmos.co/pages/test/jobs/1', 'html' => '<a href="https://employer.example.test/">Corporate website</a>']], 'mhi_job.json');
    $review = seedRun($this)[0];
    expect($review['website_candidates'])->toHaveCount(1)->and($review['website_candidates'][0]['source_type'])->toBe('ats_html');
});

test('crawler explicit company field is reusable with row locator', function () {
    $company = seedCompany();
    seedSnapshot([['company_name' => $company->name, 'source_url' => 'https://agent.example.test/job/1', 'company_url' => 'https://employer.example.test/']]);
    $review = seedRun($this)[0];
    expect($review['website_candidates'][0]['evidence_field'])->toBe('company_url')
        ->and($review['website_candidates'][0]['evidence_locator'])->toContain('#row=0')
        ->and($review['website_candidates'][0]['snapshot_sha256'])->toHaveLength(64);
});

test('Agent Platform social search and ATS URLs cannot become company seeds', function () {
    $company = seedCompany();
    Agency::create(['name' => '紹介会社', 'website_url' => 'https://custom-agent.example.test/']);
    $urls = ['https://www.r-agent.com/', 'https://jobviewtrack.com/', 'https://www.careerjet.jp/', 'https://custom-agent.example.test/', 'https://youtube.com/', 'https://google.com/', 'https://hrmos.co/', 'https://unknown.example.test/jobs/1'];
    seedSnapshot(array_map(fn ($url) => ['company_name' => $company->name, 'source_url' => 'https://agent.example.test/job/1', 'company_url' => $url], $urls));
    expect(seedRun($this)[0]['resolution_status'])->toBe('not_resolved');
});

test('only observed root URL is extracted from text without generating a root from deep links', function () {
    $company = seedCompany();
    seedJob($company, '企業情報 https://employer.example.test/ 製品 https://products.example.test/item/1');
    $review = seedRun($this)[0];
    expect(array_column($review['website_candidates'], 'url'))->toBe(['https://employer.example.test/'])
        ->and($review['website_candidates'][0]['evidence_excerpt'])->toContain('https://employer.example.test/');
});

test('no evidence means not_resolved and generated review cannot become new evidence', function () {
    $company = seedCompany();
    File::put(storage_path('app/private/crawler/direct_company_website_review.json'), json_encode([['company_name' => $company->name, 'company_url' => 'https://invented.example.test/']]));
    $review = seedRun($this)[0];
    expect($review['resolution_status'])->toBe('not_resolved')->and($review['website_candidates'])->toBe([]);
});

test('deduplicated URL retains multiple evidence records and output is idempotent', function () {
    $company = seedCompany('https://employer.example.test/');
    seedSnapshot([['company_name' => $company->name, 'source_url' => 'https://agent.example.test/job/1', 'company_url' => 'https://employer.example.test/?utm_source=test#top']]);
    $first = seedRun($this);
    $second = seedRun($this);
    expect($first)->toBe($second)->and($first[0]['website_candidates'])->toHaveCount(1)
        ->and($first[0]['website_candidates'][0]['evidence'])->toHaveCount(2);
});

test('limit is company based and seed review is harmless to legacy processor', function () {
    seedCompany();
    seedCompany();
    expect(seedRun($this, ['--limit' => 1]))->toHaveCount(1);
    $this->artisan('jobdd:process-direct-reverse-lookup-queue', ['--input' => str_replace(base_path().'/', '', storage_path('app/private/crawler/direct_company_website_review.json'))])->assertSuccessful();
    expect(JobPosting::count())->toBe(0)->and(Source::count())->toBe(0)->and(ApplicationRoute::count())->toBe(0);
});


test('plain JSON does not bypass organization identity validation', function () {
    $company = seedCompany();
    seedJob($company, json_encode(['@type' => 'Organization', 'name' => '別会社', 'url' => 'https://unrelated.example.test/']));
    expect(seedRun($this)[0]['website_candidates'])->toBe([]);
});
