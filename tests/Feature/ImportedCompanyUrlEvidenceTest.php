<?php

use App\Models\ApplicationRoute;
use App\Models\Company;
use App\Models\JobPosting;
use App\Services\DirectLookup\CompanyUrlEvidence;
use App\Services\DirectLookup\CompanyWebsiteResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->oldEvidenceStorage = storage_path();
    $this->evidenceStorage = base_path('storage/framework/testing/url-evidence-'.Str::uuid());
    app()->useStoragePath($this->evidenceStorage);
    File::ensureDirectoryExists(storage_path('app/private/crawler'));
    Storage::forgetDisk('local');
    config(['filesystems.disks.local.root' => storage_path('app/private')]);
    DB::table('agencies')->insert([
        ['id' => 7, 'name' => 'Meitec'], ['id' => 8, 'name' => 'Recruit'],
    ]);
    DB::table('platforms')->insert(['id' => 1, 'name' => 'Careerjet']);
    $this->company = Company::create(['name' => 'Evidence企業'.Str::uuid(), 'website_url' => 'https://existing.example.test/']);
});

afterEach(function () {
    app()->useStoragePath($this->oldEvidenceStorage);
    Storage::forgetDisk('local');
    File::deleteDirectory($this->evidenceStorage);
});

function importUrlEvidence($test, string $provider, array $urls): void
{
    // This fixture exercises the legacy evidence importer, not live onboarding approval.
    config(['discovery.provider_capabilities.'.$provider.'.mode' => 'persistent',
        'discovery.provider_capabilities.'.$provider.'.supports_persistent_identity' => true]);
    $commands = ['careerjet' => 'crawler:import-careerjet-job', 'recruit_agent' => 'crawler:import-recruit-agent-jobs', 'meitec_next' => 'crawler:import-meitec-next-jobs'];
    $source = 'https://'.str_replace('_', '-', $provider).'.example.test/job/1';
    $row = [
        'company' => $test->company->name, 'company_name' => $test->company->name,
        'title' => '機械設計', 'region' => '大阪府', 'source_url' => $source, 'url' => $source,
        'external_id' => 'job-1', 'company_url_evidence' => array_map(fn ($url) => [
            'source_provider' => $provider, 'source_url' => $source, 'company_name' => $test->company->name,
            'raw_field' => '$.hiringOrganization.sameAs[0]', 'raw_value' => $url,
            'fetched_at' => '2026-09-13T00:00:00+00:00', 'review_status' => 'confirmed',
        ], $urls),
    ];
    File::put(storage_path('app/private/crawler/'.$provider.'_jobs.json'), json_encode(['completed' => false, 'jobs' => [$row]]));
    $test->artisan($commands[$provider])->assertSuccessful();
}

test('each importer preserves conflicting evidence idempotently and resolver reads DB without raw file', function ($provider) {
    importUrlEvidence($this, $provider, ['https://first.example.test/about']);
    $before = JobPosting::sole()->company_url_evidence;
    importUrlEvidence($this, $provider, ['https://first.example.test/about']);
    expect(JobPosting::sole()->company_url_evidence)->toBe($before);
    importUrlEvidence($this, $provider, ['https://second.example.test/']);
    importUrlEvidence($this, $provider, []);
    $job = JobPosting::sole();
    expect($job->company_url_evidence)->toHaveCount(2)
        ->and($this->company->fresh()->website_url)->toBe('https://existing.example.test/');
    File::delete(storage_path('app/private/crawler/'.$provider.'_jobs.json'));
    $review = app(CompanyWebsiteResolver::class)->resolve([
        'company_id' => $this->company->id, 'company_name' => $this->company->name, 'discovery_candidates' => [],
    ]);
    expect(array_column($review['website_candidates'], 'url'))->toBe([
        'https://existing.example.test/', 'https://first.example.test/about', 'https://second.example.test/',
    ])->and($review['status'])->toBe('unverified')
        ->and($review['website_candidates'][1]['review_status'])->toBe('needs_review')
        ->and($review['website_candidates'][1]['source_type'])->toBe('imported_company_url')
        ->and($review['website_candidates'][1]['evidence_locator'])->toBe("job_postings:{$job->id}.company_url_evidence")
        ->and($review['search']['status'])->toBe('not_needed');
})->with(['careerjet', 'recruit_agent', 'meitec_next']);

test('missing URL evidence does not invent values and partial import leaves unseen routes available', function ($provider) {
    $job = JobPosting::create(['company_id' => $this->company->id, 'title' => '未取得求人']);
    $route = ApplicationRoute::create([
        'job_posting_id' => $job->id, 'route_type' => 'agent', 'agency_id' => 7,
        'application_url' => 'https://source.example.test/old', 'availability_status' => 'available',
        'provider_key' => $provider, 'external_id' => 'old-job',
    ]);
    importUrlEvidence($this, $provider, []);
    expect(JobPosting::where('external_id', 'job-1')->sole()->company_url_evidence)->toBeNull()
        ->and($route->fresh()->availability_status)->toBe('available');
})->with(['careerjet', 'recruit_agent', 'meitec_next']);

test('evidence rolls back with an unsuccessful job transaction', function () {
    $job = JobPosting::create(['company_id' => $this->company->id, 'title' => '設計']);
    try {
        DB::transaction(function () use ($job) {
            app(CompanyUrlEvidence::class)->save($job->id, $this->company->id, [
                'company_url_evidence' => [[
                    'source_provider' => 'recruit_agent', 'company_name' => $this->company->name,
                    'source_url' => 'https://source.example.test/job',
                    'raw_field' => '$.company_url', 'raw_value' => 'https://company.example.test/',
                    'fetched_at' => '2026-09-13T00:00:00Z',
                ]],
            ], 'recruit_agent');
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }
    expect($job->fresh()->company_url_evidence)->toBeNull();
});

test('resolver also reads the new evidence directly from a crawler snapshot', function () {
    File::put(storage_path('app/private/crawler/recruit_agent_url_evidence_slice.json'), json_encode(['jobs' => [[
        'company_name' => $this->company->name,
        'source_url' => 'https://source.example.test/job',
        'company_url_evidence' => [[
            'company_name' => $this->company->name, 'source_provider' => 'recruit_agent',
            'source_url' => 'https://source.example.test/job', 'raw_field' => '$.hiringOrganization.url',
            'raw_value' => 'https://snapshot.example.test/company', 'fetched_at' => '2026-09-13T00:00:00Z',
        ]],
    ]]]));
    $review = app(CompanyWebsiteResolver::class)->resolve([
        'company_id' => $this->company->id, 'company_name' => $this->company->name, 'discovery_candidates' => [],
    ]);
    expect($review['website_candidates'][1]['url'])->toBe('https://snapshot.example.test/company')
        ->and($review['website_candidates'][1]['source_type'])->toBe('crawler_json')
        ->and($review['website_candidates'][1]['raw_value'])->toBe('https://snapshot.example.test/company')
        ->and($review['website_candidates'][1]['review_status'])->toBe('needs_review')
        ->and(JobPosting::count())->toBe(0);
});
