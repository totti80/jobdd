<?php

use App\Services\DailyDiscoveryImporter;
use App\Services\DailyDiscoveryService;
use App\Services\DiscoveryProviderAdapter;
use App\Support\ProviderCapabilities;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->reportDirectory = storage_path('framework/testing/provider-'.Str::uuid());
    config(['discovery.report_directory' => $this->reportDirectory]);
});

afterEach(function () {
    File::deleteDirectory($this->reportDirectory);
});

function onboardingRow(): array
{
    return ['company' => null, 'title' => '機械設計', 'locations' => '兵庫県明石市',
        'description' => 'AutoCADを使用して設計', 'url' => 'https://jobs.provider-fixture.jp/track',
        'salary_min' => 300000, 'salary_type' => 'M'];
}

test('careerjet normal and dry commands observe only without SQL writes or historical classification', function ($dry) {
    $adapter = Mockery::mock(DiscoveryProviderAdapter::class)->makePartial();
    $adapter->shouldReceive('fetch')->once()->andReturn(['jobs' => [onboardingRow()], 'completed' => true, 'metadata' => ['hits' => 100]]);
    app()->instance(DiscoveryProviderAdapter::class, $adapter);
    DB::enableQueryLog();
    DB::flushQueryLog();
    $report = app(DailyDiscoveryService::class)->run($dry, 'careerjet', '機械設計', '兵庫県');
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    expect(array_filter($queries, fn ($q) => ! preg_match('/^select\b/i', $q['query'])))->toBe([])
        ->and($report['status'])->toBe('success')->and($report['direct_lookup_candidates'])->toBe([])
        ->and($report['by_provider']['careerjet'])->toMatchArray(['mode' => 'read_only', 'new' => null,
            'updated' => null, 'unchanged' => null, 'missing' => null, 'not_observed_in_window' => null,
            'observed_in_window' => 1, 'accepted' => 1, 'anonymous' => 1, 'hits' => 100, 'imported' => 0]);
    $cell = $report['by_cell'][0];
    expect($cell['current_discovery_candidates'])->toHaveCount(1)->and($cell['observation_time'])->not->toBeEmpty();
    $md = file_get_contents(str_replace('.json', '.md', $report['report_path']));
    expect($md)->toContain('N/A', 'observed=1', 'accepted=1')->not->toContain('AutoCADを使用して設計');
    expect(DB::table('companies')->count())->toBe(0)->and(DB::table('job_postings')->count())->toBe(0);
})->with([true, false]);

test('read only importer and legacy careerjet command cannot bypass the mode', function () {
    DB::enableQueryLog();
    DB::flushQueryLog();
    $result = app(DailyDiscoveryImporter::class)->run('careerjet', ['occupation' => '機械設計', 'region' => '兵庫県'], [onboardingRow()], false);
    expect(DB::getQueryLog())->toBe([])->and($result['accepted'])->toBe(1);
    $this->artisan('crawler:import-careerjet-job')->assertFailed();
    $this->artisan('crawler:import-recruit-agent-jobs')->assertFailed();
    expect(DB::table('crawl_runs')->count())->toBe(0);
    DB::disableQueryLog();
});

test('persistent capability requires identity and gates direct and missing independently', function () {
    expect(ProviderCapabilities::persistent('careerjet'))->toBeFalse()
        ->and(ProviderCapabilities::persistent('unconfigured'))->toBeFalse();
    config(['discovery.provider_capabilities.recruit_agent' => [
        'mode' => 'persistent', 'supports_persistent_identity' => true,
        'supports_complete_snapshot' => false, 'supports_missing_detection' => false,
        'supports_direct_candidate_generation' => false,
    ]]);
    DB::table('agencies')->insert(['id' => 8, 'name' => 'Recruit']);
    $row = [...onboardingRow(), 'company_name' => '検証会社', 'region' => '兵庫県', 'external_id' => 'native-one',
        'source_url' => 'https://jobs.provider-fixture.jp/stable'];
    $adapter = Mockery::mock(DiscoveryProviderAdapter::class)->makePartial();
    $adapter->shouldReceive('fetch')->andReturn(['jobs' => [$row], 'completed' => true]);
    app()->instance(DiscoveryProviderAdapter::class, $adapter);
    $first = app(DailyDiscoveryService::class)->run(false, 'recruit_agent', '機械設計', '兵庫県');
    $second = app(DailyDiscoveryService::class)->run(false, 'recruit_agent', '機械設計', '兵庫県');
    expect($first['by_provider']['recruit_agent']['new'])->toBe(1)
        ->and($second['by_provider']['recruit_agent']['unchanged'])->toBe(1)
        ->and($first['direct_lookup_candidates'])->toBe([]);
    $empty = app(DailyDiscoveryImporter::class)->run('recruit_agent', ['occupation' => '機械設計', 'region' => '兵庫県'], [], true);
    expect($empty['missing'])->toBe(0)->and($empty['not_observed_in_window'])->toBe(1);
    config(['discovery.provider_capabilities.recruit_agent.supports_complete_snapshot' => true]);
    expect(app(DailyDiscoveryImporter::class)->run('recruit_agent', ['occupation' => '機械設計', 'region' => '兵庫県'], [], true)['missing'])->toBe(0);
    config(['discovery.provider_capabilities.recruit_agent.supports_missing_detection' => true]);
    expect(app(DailyDiscoveryImporter::class)->run('recruit_agent', ['occupation' => '機械設計', 'region' => '兵庫県'], [], true)['missing'])->toBe(1);
});
