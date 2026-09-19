<?php

use App\Models\Company;
use App\Models\JobFact;
use App\Models\JobPosting;
use App\Models\UserQuery;
use App\Services\JobFitRunnerService;
use Illuminate\Support\Facades\DB;

test('preloads candidates with two constant queries and performs no SQL during both evaluation loops', function ($count) {
    $company = Company::create(['name' => 'Slice fixture']);
    $storedQuery = UserQuery::create(['raw_text' => 'TEST ONLY', 'occupation' => '機械設計', 'region' => '兵庫県', 'salary_min' => 600]);
    for ($i = 0; $i < $count; $i++) {
        $job = JobPosting::create(['company_id' => $company->id, 'title' => '機械設計', 'occupation' => '機械設計',
            'region' => $i % 2 ? '大阪府' : '兵庫県', 'description' => '【使用ツール】AutoCAD',
            'source_url' => 'https://example.test/jobs/'.$i, 'salary_max' => $i % 2 ? 500 : null]);
        if ($i % 3 !== 0) {
            JobFact::create(['job_posting_id' => $job->id, 'fact_category' => 'tool', 'fact_key' => 'autocad',
                'fact_value' => 'AutoCAD', 'normalized_value' => 'AutoCAD', 'extraction_method' => 'rule',
                'verification_status' => 'verified', 'evidence_text' => 'AutoCAD', 'observed_at' => '2026-09-19 00:00:00']);
        }
    }
    // Fixture setup above is only in the testing database; measure only the slice below.
    $sql = [];
    $record = true;
    DB::listen(function ($event) use (&$sql, &$record) {
        if ($record) {
            $sql[] = $event->sql;
        }
    });
    try {
        $query = UserQuery::findOrFail($storedQuery->id);
        expect($sql)->toHaveCount(1);
        $jobs = JobPosting::query()->where('occupation', $query->occupation)
            ->whereIn('region', ['兵庫県', '大阪府', '京都府', '滋賀県', '奈良県', '和歌山県'])
            ->whereNull('unavailable_at')->with('jobFacts')->orderBy('id')->limit(30)->get();
        expect($sql)->toHaveCount(3)->and($jobs)->toHaveCount($count);
        $facts = [];
        foreach ($jobs as $job) {
            expect($job->relationLoaded('jobFacts'))->toBeTrue()->and($job->relationLoaded('company'))->toBeFalse();
            $facts[$job->id] = $job->getRelation('jobFacts')->all();
        }
        $beforeLoop = count($sql);
        $requirements = ['desired' => [['fact_key' => 'autocad', 'action' => 'use']], 'hard_axes' => ['region']];
        $runner = app(JobFitRunnerService::class);
        $results = $runner->run($query, $jobs, $facts, $requirements);
        expect($runner->run($query, $jobs, $facts, $requirements))->toBe($results)
            ->and(count($sql) - $beforeLoop)->toBe(0)
            ->and(array_column($results, 'job_posting_id'))->toBe($jobs->pluck('id')->all());
        expect(array_filter($sql, fn ($q) => ! preg_match('/^select\b/i', $q)))->toBe([]);
        expect($results[0]['axes'][3]['reason_code'])->toBe('presence_not_found');
        if ($count > 1) {
            expect($results[1]['summary']['hard_mismatch_keys'])->toBe(['region'])
                ->and($results[1]['axes'][3]['status'])->toBe('match');
        }
    } finally {
        $record = false;
    }
})->with([1, 10, 20]);
