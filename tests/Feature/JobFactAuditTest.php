<?php

use App\Models\Company;
use App\Models\JobFact;
use App\Models\JobPosting;
use App\Services\JobFactAuditService;
use App\Services\JobFactExtractor;

function makeAuditJob(string $region, string $occupation, string $description, ?string $provider = null): JobPosting
{
  $company = Company::create(['name' => "監査企業{$region}{$occupation}{$description}"]);

  return JobPosting::create([
    'company_id' => $company->id,
    'title' => '監査求人',
    'occupation' => $occupation,
    'region' => $region,
    'provider_key' => $provider,
    'description' => $description,
  ]);
}

test('audit samples only the 12 allowed cells and excludes unavailable jobs', function () {
  makeAuditJob('兵庫県神戸市', '機械設計', 'Inventor');
  makeAuditJob('兵庫県', '施工管理', 'PLC');
  $unavailable = makeAuditJob('兵庫県', '機械設計', 'AutoCAD');
  $unavailable->update(['unavailable_at' => now()]);
  makeAuditJob('東京都', '機械設計', 'AutoCAD');

  $report = app(JobFactAuditService::class)->audit(app(JobFactExtractor::class), 10);

  expect($report['job_count'])->toBe(1)
    ->and(count($report['cells']))->toBe(12)
    ->and(collect($report['cells'])->sum('job_count'))->toBe(1)
    ->and(collect($report['cells'])->pluck('occupation'))->not->toContain('施工管理');
});

test('audit sampling is reproducible and evenly includes oldest and newest jobs', function () {
  foreach (range(1, 5) as $index) {
    makeAuditJob('大阪府', '電気設計', "PLC {$index}");
  }

  $service = app(JobFactAuditService::class);
  $first = $service->sampleCell('大阪府', '電気設計', 3)->pluck('id')->all();
  $second = $service->sampleCell('大阪府', '電気設計', 3)->pluck('id')->all();

  expect($first)->toBe($second)
    ->and($first)->toHaveCount(3)
    ->and($first[0])->toBeLessThan($first[1])
    ->and($first[1])->toBeLessThan($first[2]);
});

test('audit reports priority facts and does not write job facts', function () {
  $job = makeAuditJob('京都府', '機械設計', 'NX FEM CAE FA');
  $before = JobFact::count();

  $report = app(JobFactAuditService::class)->audit(app(JobFactExtractor::class), 10);

  expect($report['fact_count'])->toBe(4)
    ->and(collect($report['priority_facts'])->pluck('fact_key')->all())
    ->toBe(['nx', 'fem', 'cae', 'fa'])
    ->and($report['priority_facts'][0]['job_id'])->toBe($job->id)
    ->and(JobFact::count())->toBe($before);
});

test('audit command is a dry-run and includes priority output', function () {
  makeAuditJob('奈良県', '電気設計', 'ECAD PLC 制御盤');

  $this->artisan('jobdd:audit-job-facts', ['--limit-per-cell' => 10])
    ->expectsOutputToContain('=== Cell Summary ===')
    ->expectsOutputToContain('=== Suspicious Patterns / Review Candidates ===')
    ->expectsOutputToContain('=== Priority Fact Detail ===')
    ->expectsOutputToContain('| [electrical_cad] ECAD -> 電気CAD')
    ->expectsOutputToContain('Mode: audit dry-run (no database writes)')
    ->assertExitCode(0);

  expect(JobFact::count())->toBe(0);
});
