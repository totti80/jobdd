<?php

use App\Models\ApplicationRoute;
use App\Models\Company;
use App\Models\DirectReverseLookupCandidate;
use App\Models\JobFact;
use App\Models\JobPosting;
use App\Services\DailyDiscoveryImporter;
use App\Services\DailyDiscoveryService;
use App\Services\DirectLookup\CompanyWebsiteResolver;
use App\Services\DirectLookup\OfficialPageDiscovery;
use App\Services\DiscoveryCoverageService;
use App\Services\DiscoveryProviderAdapter;
use App\Services\JobFactExtractor;
use App\Support\AnonymousCompany;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->dailyDirectory = storage_path('framework/testing/daily-'.Str::uuid());
    config(['discovery.report_directory' => $this->dailyDirectory]);
    DB::table('agencies')->insert([['id' => 7, 'name' => 'Meitec'], ['id' => 8, 'name' => 'Recruit']]);
    DB::table('platforms')->insert(['id' => 1, 'name' => 'Careerjet']);
});

afterEach(function () {
    File::deleteDirectory($this->dailyDirectory);
});

function dailyRow(string $id = 'one', array $extra = []): array
{
    return [...['company' => '日次検証会社', 'company_name' => '日次検証会社', 'title' => '機械設計',
        'region' => '兵庫県', 'url' => 'https://jobs.sample-company.jp/'.$id, 'source_url' => 'https://jobs.sample-company.jp/'.$id,
        'external_id' => $id, 'description' => 'AutoCADを使用する機械設計業務',
        'salary_min' => 500, 'salary_max' => 800, 'salary_type' => 'Y'], ...$extra];
}

function dailyCell(): array
{
    return ['occupation' => '機械設計', 'region' => '兵庫県'];
}

function dailyFetchFake($test, Closure $fetch): void
{
    $adapter = Mockery::mock(DiscoveryProviderAdapter::class)->makePartial();
    $adapter->shouldReceive('fetch')->andReturnUsing($fetch);
    app()->instance(DiscoveryProviderAdapter::class, $adapter);
}

test('daily matrix and filters are deterministic and reject invalid scopes', function () {
    $service = app(DailyDiscoveryService::class);
    $cells = $service->cells();
    expect($cells)->toHaveCount(12)->and($cells[0])->toBe(dailyCell())
        ->and($cells[6])->toBe(['occupation' => '電気設計', 'region' => '兵庫県'])
        ->and($cells[11])->toBe(['occupation' => '電気設計', 'region' => '和歌山県'])
        ->and($service->cells(null, '京都府'))->toHaveCount(2);
    $this->artisan('jobdd:discover-daily', ['--provider' => 'invalid'])->expectsOutput('InvalidProvider')->assertFailed();
    $this->artisan('jobdd:discover-daily', ['--region' => '東京都'])->expectsOutput('InvalidCell')->assertFailed();
});

test('daily integrates all existing importer commands with stable identities and freshness', function ($provider) {
    $this->travelTo(now()->startOfSecond());
    $row = dailyRow();
    $adapter = app(DiscoveryProviderAdapter::class);
    $first = $adapter->import($provider, dailyCell(), ['jobs' => [$row]], false);
    expect($first['new'])->toBe(1)->and($first['imported'])->toBe(1);
    $job = JobPosting::sole();
    $firstSeen = $job->first_seen_at->toDateTimeString();
    $factTime = JobFact::first()->observed_at->toDateTimeString();
    $this->travel(1)->days();
    $second = $adapter->import($provider, dailyCell(), ['jobs' => [$row, $row]], false);
    expect($second['unchanged'])->toBe(1)->and($second['new'])->toBe(0)
        ->and(JobPosting::count())->toBe(1)->and(ApplicationRoute::count())->toBe(1)
        ->and($job->fresh()->first_seen_at->toDateTimeString())->toBe($firstSeen)
        ->and($job->fresh()->last_seen_at->toDateTimeString())->not->toBe($firstSeen)
        ->and(JobFact::first()->observed_at->toDateTimeString())->toBe($factTime);
    $changed = dailyRow(extra: ['title' => '機械設計・更新', 'description' => 'SolidWorksを使用した機械設計']);
    $third = $adapter->import($provider, dailyCell(), ['jobs' => [$changed]], false);
    expect($third['updated'])->toBe(1)->and(JobPosting::sole()->id)->toBe($job->id)
        ->and(JobPosting::sole()->title)->toBe('機械設計・更新')
        ->and(JobFact::where('fact_key', 'solidworks')->exists())->toBeTrue()
        ->and(JobFact::where('fact_key', 'autocad')->exists())->toBeFalse();
    $count = JobFact::count();
    $adapter->import($provider, dailyCell(), ['jobs' => [$changed]], false);
    expect(JobFact::count())->toBe($count)->and(ApplicationRoute::sole()->first_seen_at->toDateTimeString())->toBe($firstSeen);
})->with(['careerjet', 'recruit_agent', 'meitec_next']);

test('missing is observation only and failed rows do not generate missing observations', function () {
    $importer = app(DailyDiscoveryImporter::class);
    $importer->run('recruit_agent', dailyCell(), [dailyRow()], false);
    $before = JobPosting::sole()->toArray();
    $result = $importer->run('recruit_agent', dailyCell(), [], false);
    expect($result['missing'])->toBe(1)->and(JobPosting::sole()->toArray())->toBe($before)
        ->and(ApplicationRoute::sole()->availability_status)->toBe('available')
        ->and(ApplicationRoute::sole()->unavailable_at)->toBeNull();
    $failed = $importer->run('recruit_agent', dailyCell(), [['title' => 'broken']], false);
    expect($failed['errors'])->toHaveCount(1)->and($failed['missing'])->toBe(0);
});

test('daily dry run has zero SQL writes including facts routes cache and crawl runs', function () {
    app(DailyDiscoveryImporter::class)->run('meitec_next', dailyCell(), [dailyRow('old')], false);
    dailyFetchFake($this, fn () => ['completed' => true, 'jobs' => [dailyRow('new')]]);
    $tables = ['companies', 'job_postings', 'sources', 'job_facts', 'application_routes', 'crawl_runs', 'cache', 'cache_locks'];
    $snapshot = fn () => collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->get()->toJson()])->all();
    $before = $snapshot();
    $sql = [];
    $record = true;
    DB::listen(function ($event) use (&$sql, &$record) {
        if ($record) {
            $sql[] = $event->sql;
        }
    });
    try {
        $report = app(DailyDiscoveryService::class)->run(true, 'meitec_next', '機械設計', '兵庫県');
    } finally {
        $record = false;
    }
    expect(array_filter($sql, fn ($q) => ! preg_match('/^select\b/i', $q)))->toBe([])
        ->and($snapshot())->toBe($before)->and($report['by_provider']['meitec_next']['new'])->toBe(1)
        ->and($report['by_provider']['meitec_next']['missing'])->toBe(1)
        ->and($report['by_provider']['meitec_next']['imported'])->toBe(0)
        ->and($report['direct_lookup_candidates'][0]['job_ids'])->toBe([])
        ->and(is_file($report['report_path']))->toBeTrue();
});

test('provider failure is isolated and reports secrets neither in JSON nor Markdown', function () {
    dailyFetchFake($this, function ($provider, $cell) {
        if ($provider === 'careerjet') {
            throw new RuntimeException('SECRET-API-KEY response full HTML');
        }

        return ['completed' => true, 'jobs' => [dailyRow($provider)]];
    });
    $report = app(DailyDiscoveryService::class)->run(false, null, '機械設計', '兵庫県');
    expect($report['status'])->toBe('partial')->and($report['by_cell'])->toHaveCount(3)
        ->and($report['errors'][0])->toMatchArray(['provider' => 'careerjet', 'occupation' => '機械設計', 'region' => '兵庫県', 'error_type' => 'RuntimeException'])
        ->and($report['by_cell'][0]['missing'])->toBeNull()->and(JobPosting::count())->toBe(2)
        ->and($report['direct_lookup_candidates'])->toHaveCount(1);
    $json = file_get_contents($report['report_path']);
    $md = file_get_contents(str_replace('.json', '.md', $report['report_path']));
    expect($md)->toContain('## Coverage', '## Evidence Depth', '## Errors', '## Cells');
    expect($json.$md)->not->toContain('SECRET-API-KEY')->and($report['coverage']['evidence_depth']['jobs_with_any_fact'])->toBe(2)
        ->and($report['cells'][0]['after_import'])->toBe(2);
});

test('all twelve cells run in order per provider and report current coverage for empty cells', function () {
    $calls = [];
    dailyFetchFake($this, function ($provider, $cell) use (&$calls) {
        $calls[] = [$provider, $cell];

        return ['completed' => true, 'jobs' => []];
    });
    $report = app(DailyDiscoveryService::class)->run(true);
    expect($calls)->toHaveCount(36)->and($calls[0])->toBe(['careerjet', dailyCell()])
        ->and($calls[12])->toBe(['recruit_agent', dailyCell()])->and($calls[24])->toBe(['meitec_next', dailyCell()])
        ->and($report['cells'])->toHaveCount(12)->and($report['cell_count'])->toBe(12)
        ->and($report['provider_count'])->toBe(3)->and($report['status'])->toBe('success');
});

test('direct candidates are changed companies only and preserve existing route and source information', function () {
    $importer = app(DailyDiscoveryImporter::class);
    $first = $importer->run('meitec_next', dailyCell(), [dailyRow('one'), dailyRow('two')], false);
    $company = Company::sole();
    $company->update(['website_url' => 'https://company.sample.jp']);
    ApplicationRoute::create(['job_posting_id' => JobPosting::first()->id, 'route_type' => 'direct', 'availability_status' => 'available']);
    $coverage = app(DiscoveryCoverageService::class);
    $candidates = $coverage->directCandidates($first['changes']);
    expect($candidates)->toHaveCount(1)->and($candidates[0]['job_ids'])->toHaveCount(2)
        ->and($candidates[0]['existing_direct_route'])->toBeTrue()->and($candidates[0]['official_source_known'])->toBeTrue();
    $second = $importer->run('meitec_next', dailyCell(), [dailyRow('one'), dailyRow('two')], false);
    expect($coverage->directCandidates($second['changes']))->toBe([]);
});

test('daily lock covers manual runs and scheduler registers an inert daily event', function () {
    File::ensureDirectoryExists($this->dailyDirectory);
    $lock = fopen($this->dailyDirectory.'/.lock', 'c');
    flock($lock, LOCK_EX);
    try {
        $this->artisan('jobdd:discover-daily', ['--dry-run' => true])->expectsOutput('AlreadyRunning')->assertFailed();
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command, 'jobdd:discover-daily'));
    expect($event)->not->toBeNull()->and($event->expression)->toBe('0 4 * * *')
        ->and($event->timezone)->toBe('Asia/Tokyo')->and($event->withoutOverlapping)->toBeTrue()
        ->and(config('discovery.enabled'))->toBeFalse();
});

test('source approval failure performs no fetch and does not assert missing', function () {
    config(['discovery.approved_providers' => []]);
    $report = app(DailyDiscoveryService::class)->run(true, 'careerjet', '機械設計', '兵庫県');
    expect($report['status'])->toBe('failed')->and($report['errors'][0]['error_type'])->toBe('ProviderNotApproved')
        ->and($report['by_cell'][0]['missing'])->toBeNull();
});

test('changed source fields persist while first seen remains and failed fact work rolls back job', function () {
    $importer = app(DailyDiscoveryImporter::class);
    $importer->run('recruit_agent', dailyCell(), [dailyRow()], false);
    $result = $importer->run('recruit_agent', dailyCell(), [dailyRow(extra: ['salary_min' => 700, 'provider_updated_at' => '2026-09-21 00:00:00'])], false);
    expect($result['updated'])->toBe(1)->and(JobPosting::sole()->salary_min)->toBe(700);
    $job = JobPosting::sole()->toArray();
    $extractor = Mockery::mock(JobFactExtractor::class)->makePartial();
    $extractor->shouldReceive('persist')->andThrow(new RuntimeException('secret exception'));
    $this->app->instance(JobFactExtractor::class, $extractor);
    $failed = $importer->run('recruit_agent', dailyCell(), [dailyRow(extra: ['description' => '別の機械設計業務'])], false);
    expect($failed['errors'])->toHaveCount(1)->and(JobPosting::sole()->toArray())->toBe($job);
});

test('daily normalization never uses a search keyword as occupation or collapses multiple prefectures', function () {
    $importer = app(DailyDiscoveryImporter::class);
    $result = $importer->run('recruit_agent', dailyCell(), [
        dailyRow('other', ['title' => 'ソフトウェア開発', 'occupation' => '機械設計']),
        dailyRow('multiple', ['region' => '東京都 / 兵庫県']),
    ], false);
    expect($result['skipped'])->toBe(2)->and(JobPosting::count())->toBe(0);
    expect($importer->normalize('careerjet', dailyRow(extra: ['salary_min' => 6000000, 'salary_max' => 8000000]))['salary_min'])->toBe(600);
});

test('legacy shared provider identity is reported instead of reassigned', function () {
    app(DailyDiscoveryImporter::class)->run('recruit_agent', dailyCell(), [dailyRow()], false);
    $job = JobPosting::sole();
    ApplicationRoute::create(['job_posting_id' => $job->id, 'route_type' => 'agent', 'agency_id' => 7,
        'provider_key' => 'meitec_next', 'external_id' => 'one', 'availability_status' => 'available']);
    $before = $job->toArray();
    $result = app(DailyDiscoveryImporter::class)->run('meitec_next', dailyCell(), [dailyRow()], false);
    expect($result['errors'])->toHaveCount(1)->and(JobPosting::sole()->toArray())->toBe($before)
        ->and(ApplicationRoute::where('provider_key', 'meitec_next')->sole()->job_posting_id)->toBe($job->id);
});

test('coverage uses bounded aggregate queries independent of job and fact volume', function () {
    app(DailyDiscoveryImporter::class)->run('recruit_agent', dailyCell(), [dailyRow()], false);
    $measure = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $report = app(DiscoveryCoverageService::class)->snapshot();

            return [$report, count(DB::getQueryLog())];
        } finally {
            DB::disableQueryLog();
        }
    };
    [$one, $queryCount] = $measure();
    app(DailyDiscoveryImporter::class)->run('recruit_agent', dailyCell(), array_map(fn ($i) => dailyRow('many-'.$i), range(1, 20)), false);
    [$many, $manyQueryCount] = $measure();
    expect($manyQueryCount)->toBe($queryCount)->and($many['current_db_coverage']['active_jobs'])->toBe(21)
        ->and($many['evidence_depth']['jobs_with_any_fact'])->toBe(21);
});

test('conflicting job and route identities never repoint existing routes', function () {
    $importer = app(DailyDiscoveryImporter::class);
    $importer->run('recruit_agent', dailyCell(), [dailyRow('one'), dailyRow('two')], false);
    $two = JobPosting::where('external_id', 'two')->sole();
    $route = ApplicationRoute::where('external_id', 'one')->sole();
    $route->update(['job_posting_id' => $two->id]);
    $result = $importer->run('recruit_agent', dailyCell(), [dailyRow('one')], false);
    expect($result['errors'])->toHaveCount(1)->and($route->fresh()->job_posting_id)->toBe($two->id);
});

test('anonymous careerjet jobs reuse a placeholder without facts or direct identity loss', function () {
    $importer = app(DailyDiscoveryImporter::class);
    $rows = [dailyRow('null', ['company' => null]), dailyRow('empty', ['company' => '  ']), dailyRow('named')];
    $preview = $importer->run('careerjet', dailyCell(), $rows, true);
    expect($preview['errors'])->toBe([])->and($preview['anonymous_company_jobs'])->toBe(2)
        ->and(Company::count())->toBe(0)->and(JobPosting::count())->toBe(0);
    $first = $importer->run('careerjet', dailyCell(), $rows, false);
    $second = $importer->run('careerjet', dailyCell(), $rows, false);
    expect($first['errors'])->toBe([])->and($first['new'])->toBe(3)
        ->and($second['unchanged'])->toBe(3)->and($second['errors'])->toBe([])
        ->and(Company::count())->toBe(2)->and(JobPosting::count())->toBe(3)
        ->and(ApplicationRoute::count())->toBe(3)->and(JobFact::count())->toBeGreaterThanOrEqual(3);
    $anonymous = Company::where('name', AnonymousCompany::NAME)->sole();
    expect($anonymous->website_url)->toBeNull()->and($anonymous->region)->toBeNull()
        ->and($anonymous->jobPostings()->count())->toBe(2);
    $candidates = app(DiscoveryCoverageService::class)->directCandidates($first['changes']);
    expect($candidates)->toHaveCount(1)->and($candidates[0]['company_name'])->toBe('日次検証会社');
    $metrics = app(DiscoveryCoverageService::class)->snapshot()['current_db_coverage'];
    expect($metrics)->toMatchArray(['companies_total' => 2, 'named_companies' => 1, 'anonymous_companies' => 1]);
});

test('anonymous identity is reserved and isolated from other providers', function () {
    $importer = app(DailyDiscoveryImporter::class);
    expect(fn () => AnonymousCompany::name('recruit_agent'))->toThrow(InvalidArgumentException::class);
    expect(fn () => $importer->normalize('careerjet', dailyRow(extra: ['company' => AnonymousCompany::NAME])))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => $importer->normalize('recruit_agent', dailyRow(extra: ['company_name' => null])))
        ->toThrow(InvalidArgumentException::class);
    expect($importer->normalize('careerjet', dailyRow())['company_name'])->toBe('日次検証会社');
});

test('anonymous company presentation explains uncertainty and escapes named companies without queries', function () {
    DB::enableQueryLog();
    DB::flushQueryLog();
    $html = Blade::render('<x-company-name :name="$name" />', ['name' => AnonymousCompany::NAME]);
    $unsafe = Blade::render('<x-company-name :name="$name" />', ['name' => '<script>alert(1)</script>']);
    expect($html)->toContain(AnonymousCompany::LABEL, AnonymousCompany::NOTE)
        ->not->toContain(AnonymousCompany::NAME)
        ->and($unsafe)->toContain('&lt;script&gt;')->not->toContain('<script>')
        ->and(DB::getQueryLog())->toBe([]);
    DB::disableQueryLog();
});

test('anonymous employers cannot enter the persisted direct lookup pipeline', function () {
    app(DailyDiscoveryImporter::class)->run('careerjet', dailyCell(), [dailyRow(extra: ['company' => null])], false);
    $this->artisan('jobdd:build-direct-reverse-lookup')->assertSuccessful();
    expect(DirectReverseLookupCandidate::count())->toBe(0);
    $company = Company::sole();
    $company->update(['website_url' => 'https://not-an-employer.example']);
    expect(fn () => app(CompanyWebsiteResolver::class)->resolve(['company_id' => $company->id, 'company_name' => $company->name]))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => app(OfficialPageDiscovery::class)->discover(['company_id' => $company->id]))
        ->toThrow(InvalidArgumentException::class);
});
