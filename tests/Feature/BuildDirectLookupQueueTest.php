<?php

use App\Models\ApplicationRoute;
use App\Models\Company;
use App\Models\DirectReverseLookupCandidate;
use App\Models\JobPosting;
use App\Models\Source;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->originalStorage = storage_path();
    $this->queueStorage = base_path('storage/framework/testing/direct-queue-'.Str::uuid());
    app()->useStoragePath($this->queueStorage);
});

afterEach(function () {
    app()->useStoragePath($this->originalStorage);
    File::deleteDirectory($this->queueStorage);
});

function lookupCandidate(array $attributes = []): DirectReverseLookupCandidate
{
    return DirectReverseLookupCandidate::create(array_merge([
        'company_id' => Company::create(['name' => '企業'.Str::uuid()])->id,
        'region' => '大阪府',
        'occupation' => '機械設計',
        'discovery_source' => 'recruit_agent',
        'matching_job_count' => 1,
        'direct_status' => 'unverified',
    ], $attributes));
}

function lookupQueue(): array
{
    return json_decode(File::get(storage_path('app/private/crawler/direct_lookup_queue.json')), true, 512, JSON_THROW_ON_ERROR);
}

test('major six cells precede other cells including city regions', function () {
    lookupCandidate(['region' => '京都府']);
    $ids = [];
    foreach (['大阪府大阪市', '兵庫県神戸市'] as $region) {
        foreach (['機械設計', '電気設計', '施工管理'] as $occupation) {
            $ids[] = lookupCandidate(compact('region', 'occupation'))->id;
        }
    }
    $this->artisan('jobdd:build-direct-lookup-queue', ['--limit' => 6])->assertSuccessful();
    expect(array_column(lookupQueue(), 'candidate_id'))->toBe($ids);
});

test('confirmed company cells and ineligible candidates are excluded', function () {
    $confirmed = lookupCandidate(['direct_status' => 'confirmed']);
    lookupCandidate(['company_id' => $confirmed->company_id, 'discovery_source' => 'careerjet']);
    foreach (['not_found', 'ignored', 'unavailable'] as $status) {
        lookupCandidate(['direct_status' => $status]);
    }
    lookupCandidate(['matching_job_count' => 0]);
    lookupCandidate(['region' => null]);
    lookupCandidate(['occupation' => 'ソフトウェア・IT']);
    lookupCandidate(['company_id' => Company::create(['name' => 'A製作所'])->id]);
    $valid = lookupCandidate();
    $this->artisan('jobdd:build-direct-lookup-queue')->assertSuccessful();
    expect(array_column(lookupQueue(), 'candidate_id'))->toBe([$valid->id]);
});

test('confirmed and unverified cells of one company retain all unverified cells in one queue record', function () {
    $company = Company::create(['name' => '三菱重工業株式会社']);
    lookupCandidate([
        'company_id' => $company->id, 'region' => '兵庫県',
        'occupation' => '機械設計', 'direct_status' => 'confirmed',
    ]);
    // The same confirmed cell discovered through another source is also excluded.
    lookupCandidate([
        'company_id' => $company->id, 'region' => '兵庫県',
        'occupation' => '機械設計', 'discovery_source' => 'careerjet',
    ]);
    $electrical = lookupCandidate([
        'company_id' => $company->id, 'region' => '兵庫県', 'occupation' => '電気設計',
    ]);
    $osaka = lookupCandidate([
        'company_id' => $company->id, 'region' => '大阪府', 'occupation' => '機械設計',
    ]);
    $before = DirectReverseLookupCandidate::all()->toArray();

    $this->artisan('jobdd:build-direct-lookup-queue', ['--limit' => 1])->assertSuccessful();

    expect(lookupQueue())->toHaveCount(1)
        ->and(lookupQueue()[0]['company_id'])->toBe($company->id)
        ->and(lookupQueue()[0]['candidate_id'])->toBe($electrical->id)
        ->and(lookupQueue()[0]['region'])->toBe('兵庫県')
        ->and(lookupQueue()[0]['occupation'])->toBe('電気設計')
        ->and(array_column(lookupQueue()[0]['discovery_candidates'], 'candidate_id'))->toBe([$electrical->id, $osaka->id])
        ->and(array_column(lookupQueue()[0]['discovery_candidates'], 'region'))->toBe(['兵庫県', '大阪府'])
        ->and(array_column(lookupQueue()[0]['discovery_candidates'], 'occupation'))->toBe(['電気設計', '機械設計'])
        ->and(DirectReverseLookupCandidate::all()->toArray())->toBe($before);
});

test('company deduplication preserves discovery sources and cells with stable JSON and limit', function () {
    $first = lookupCandidate();
    $second = lookupCandidate(['company_id' => $first->company_id, 'discovery_source' => 'careerjet']);
    $third = lookupCandidate(['company_id' => $first->company_id, 'region' => '兵庫県']);
    lookupCandidate();
    $before = DirectReverseLookupCandidate::all()->toArray();
    $this->artisan('jobdd:build-direct-lookup-queue', ['--limit' => 1])->assertSuccessful();
    $json = File::get(storage_path('app/private/crawler/direct_lookup_queue.json'));
    $this->artisan('jobdd:build-direct-lookup-queue', ['--limit' => 1])->assertSuccessful();
    expect(File::get(storage_path('app/private/crawler/direct_lookup_queue.json')))->toBe($json)
        ->and(lookupQueue())->toHaveCount(1)
        ->and(lookupQueue()[0]['status'])->toBe('unverified')
        ->and(lookupQueue()[0]['official_site_url'])->toBeNull()
        ->and(lookupQueue()[0]['official_recruit_url'])->toBeNull()
        ->and(array_column(lookupQueue()[0]['discovery_candidates'], 'candidate_id'))->toBe([$first->id, $second->id, $third->id])
        ->and(array_column(lookupQueue()[0]['discovery_candidates'], 'discovery_source'))->toBe(['recruit_agent', 'careerjet', 'recruit_agent'])
        ->and(DirectReverseLookupCandidate::all()->toArray())->toBe($before);
});

test('empty then thin then sufficient coverage and unverified before retry are prioritized', function () {
    $sufficient = lookupCandidate(['occupation' => '施工管理']);
    $thin = lookupCandidate(['occupation' => '電気設計']);
    $retry = lookupCandidate(['direct_status' => 'crawl_failed']);
    $pending = lookupCandidate();
    foreach (['施工管理' => 3, '電気設計' => 1] as $occupation => $count) {
        for ($i = 0; $i < $count; $i++) {
            $job = JobPosting::create([
                'company_id' => $thin->company_id, 'title' => $occupation.'担当',
                'occupation' => $occupation, 'region' => '大阪府大阪市',
            ]);
            // Duplicate routes must still count as one job.
            for ($j = 0; $j < 2; $j++) {
                ApplicationRoute::create([
                    'job_posting_id' => $job->id, 'route_type' => 'direct',
                    'availability_status' => 'available', 'application_url' => 'https://example.test/job',
                ]);
            }
        }
    }
    $this->artisan('jobdd:build-direct-lookup-queue')->assertSuccessful();
    expect(array_column(lookupQueue(), 'candidate_id'))->toBe([$pending->id, $retry->id, $thin->id, $sufficient->id])
        ->and(array_column(lookupQueue(), 'direct_coverage'))->toBe([0, 0, 1, 3]);
});

test('generated queue is accepted by processor without changing verification or evidence', function () {
    $candidate = lookupCandidate(['direct_status' => 'crawl_failed', 'checked_at' => now()->subDay()]);
    $before = $candidate->fresh()->toArray();
    $this->artisan('jobdd:build-direct-lookup-queue')->assertSuccessful();
    $input = str_replace(base_path().'/', '', storage_path('app/private/crawler/direct_lookup_queue.json'));
    $this->artisan('jobdd:process-direct-reverse-lookup-queue', ['--input' => $input])->assertSuccessful();
    expect($candidate->fresh()->toArray())->toBe($before)
        ->and(JobPosting::count())->toBe(0)
        ->and(ApplicationRoute::count())->toBe(0)
        ->and(Source::count())->toBe(0);
});

test('reviewed legacy slice remains compatible and idempotent', function () {
    $records = array_map(fn ($status) => [
        'company_name' => 'レビュー済み企業'.$status,
        'region' => '兵庫県', 'occupation' => '機械設計',
        'official_site_url' => 'https://official.example.test',
        'official_recruit_url' => 'https://official.example.test/recruit',
        'status' => $status, 'external_id' => 'reviewed-job',
        'title' => '機械設計担当', 'description' => '機械設計業務',
        'employment_type' => '正社員',
        'source_url' => 'https://official.example.test/jobs/1',
        'application_url' => 'https://official.example.test/jobs/1',
    ], ['confirmed', 'not_found', 'crawl_failed']);
    File::ensureDirectoryExists(storage_path('app/private/crawler'));
    File::put(storage_path('app/private/crawler/reviewed.json'), json_encode($records));
    $input = str_replace(base_path().'/', '', storage_path('app/private/crawler/reviewed.json'));
    foreach ($records as $record) {
        lookupCandidate([
            'company_id' => Company::create(['name' => $record['company_name']])->id,
            'region' => $record['region'], 'occupation' => $record['occupation'],
        ]);
    }
    for ($i = 0; $i < 2; $i++) {
        $this->artisan('jobdd:process-direct-reverse-lookup-queue', ['--input' => $input])->assertSuccessful();
    }
    expect(JobPosting::count())->toBe(1)
        ->and(ApplicationRoute::count())->toBe(1)
        ->and(Source::count())->toBe(1)
        ->and(DirectReverseLookupCandidate::where('direct_status', 'confirmed')->count())->toBe(1);
});

test('candidate rebuild preserves reviewed status and URLs', function () {
    $candidate = lookupCandidate(['direct_status' => 'confirmed', 'official_recruit_url' => 'https://example.test/recruit', 'checked_at' => now()]);
    $job = JobPosting::create(['company_id' => $candidate->company_id, 'title' => '機械設計担当', 'region' => '大阪府']);
    ApplicationRoute::create(['job_posting_id' => $job->id, 'route_type' => 'agent', 'provider_key' => 'recruit_agent', 'availability_status' => 'available']);
    $this->artisan('jobdd:build-direct-reverse-lookup')->assertSuccessful();
    expect($candidate->fresh()->direct_status)->toBe('confirmed')
        ->and($candidate->fresh()->official_recruit_url)->toBe('https://example.test/recruit');
});

test('invalid limits do not overwrite the queue', function (string $limit) {
    lookupCandidate();
    Artisan::call('jobdd:build-direct-lookup-queue');
    $before = lookupQueue();
    $this->artisan('jobdd:build-direct-lookup-queue', ['--limit' => $limit])->assertFailed();
    expect(lookupQueue())->toBe($before);
})->with(['0', '-1', 'abc', '1.5']);

test('no eligible candidates produces an empty JSON array', function () {
    $this->artisan('jobdd:build-direct-lookup-queue')->assertSuccessful();
    expect(lookupQueue())->toBe([]);
});
