<?php

use App\Models\Company;
use App\Models\JobFact;
use App\Models\JobPosting;
use App\Services\JobFactExtractor;

function makeExtractorJob(string $description, string $occupation = '機械設計'): JobPosting
{
  $company = Company::create(['name' => 'Extractorテスト企業']);

  return JobPosting::create([
    'company_id' => $company->id,
    'title' => 'Extractorテスト求人',
    'occupation' => $occupation,
    'region' => '兵庫県',
    'description' => $description,
  ]);
}

test('extracts aliases into canonical tier 2 facts', function () {
  $job = makeExtractorJob('Autodesk Inventor、SOLIDWORKS、CATIA V5、有限要素法、シーケンサ、構想設計から詳細設計まで。');

  $facts = app(JobFactExtractor::class)->extract($job);
  $byKey = collect($facts)->keyBy('fact_key');

  expect($byKey['inventor']['normalized_value'])->toBe('Inventor')
    ->and($byKey['solidworks']['normalized_value'])->toBe('SolidWorks')
    ->and($byKey['catia']['normalized_value'])->toBe('CATIA')
    ->and($byKey['fem']['normalized_value'])->toBe('FEM')
    ->and($byKey['plc']['normalized_value'])->toBe('PLC')
    ->and($byKey['concept_design']['normalized_value'])->toBe('構想設計')
    ->and($byKey['detail_design']['normalized_value'])->toBe('詳細設計')
    ->and($byKey['concept_design']['evidence_text'])->toContain('構想設計')
    ->and($byKey['detail_design']['evidence_text'])->toContain('詳細設計');
});

test('extracts the limited Batch 3.2 aliases with canonical values and evidence', function () {
  $job = makeExtractorJob('有限要素解析を用いた強度評価。ラダー制御による設備制御設計。');

  $facts = collect(app(JobFactExtractor::class)->extract($job))->keyBy('fact_key');

  expect($facts['fem']['normalized_value'])->toBe('FEM')
    ->and($facts['fem']['fact_value'])->toBe('有限要素解析')
    ->and($facts['fem']['evidence_text'])->toContain('有限要素解析')
    ->and($facts['plc']['normalized_value'])->toBe('PLC')
    ->and($facts['plc']['fact_value'])->toBe('ラダー制御')
    ->and($facts['plc']['evidence_text'])->toContain('ラダー制御');
});

test('preserves existing aliases with the Batch 3.2 dictionary', function () {
  $job = makeExtractorJob('有限要素法、シーケンサ、PLC、CATIA V5、Autodesk Inventor。');

  $facts = collect(app(JobFactExtractor::class)->extract($job))->keyBy('fact_key');

  expect($facts['fem']['normalized_value'])->toBe('FEM')
    ->and($facts['plc']['normalized_value'])->toBe('PLC')
    ->and($facts['catia']['normalized_value'])->toBe('CATIA')
    ->and($facts['inventor']['normalized_value'])->toBe('Inventor');
});

test('Batch 3.2 aliases remain idempotent when persisted', function () {
  $job = makeExtractorJob('有限要素解析と有限要素法、ラダー制御とPLCを担当します。');
  $extractor = app(JobFactExtractor::class);

  expect(collect($extractor->extract($job))->where('fact_key', 'fem')->count())->toBe(1)
    ->and(collect($extractor->extract($job))->where('fact_key', 'plc')->count())->toBe(1);

  $extractor->persist($job);
  $extractor->persist($job);

  expect(JobFact::where('job_posting_id', $job->id)->where('fact_key', 'fem')->count())->toBe(1)
    ->and(JobFact::where('job_posting_id', $job->id)->where('fact_key', 'plc')->count())->toBe(1);
});

test('extracts multiple electrical facts and provides evidence snippets', function () {
  $job = makeExtractorJob('PLC制御盤の回路設計と制御設計を担当します。', '電気設計');

  $facts = app(JobFactExtractor::class)->extract($job);
  $keys = collect($facts)->pluck('fact_key');

  expect($keys)->toContain('plc', 'control_panel', 'circuit_design', 'control_design')
    ->and(collect($facts)->every(fn($fact) => $fact['evidence_text'] !== ''))->toBeTrue()
    ->and(collect($facts)->every(fn($fact) => str_contains($fact['evidence_text'], $fact['fact_value'])))->toBeTrue();
});

test('does not match short ascii aliases inside larger words', function () {
  $job = makeExtractorJob('This is a PLConstruction role. AFA component is unrelated. NXA is not NX.');

  $keys = collect(app(JobFactExtractor::class)->extract($job))->pluck('fact_key');

  expect($keys)->not->toContain('plc', 'fa', 'nx');
});

test('persists the same rule fact idempotently', function () {
  $job = makeExtractorJob('Autodesk Inventorと構想設計を担当します。');
  $extractor = app(JobFactExtractor::class);

  $first = $extractor->persist($job);
  $second = $extractor->persist($job);

  expect($first)->toHaveCount(2)
    ->and($second)->toHaveCount(2)
    ->and(JobFact::where('job_posting_id', $job->id)->count())->toBe(2);
});

test('dry run command does not write facts', function () {
  $job = makeExtractorJob('Autodesk Inventorと詳細設計を担当します。');

  $this->artisan('jobdd:extract-job-facts', [
    '--dry-run' => true,
    '--job-id' => $job->id,
  ])->assertExitCode(0);

  expect(JobFact::where('job_posting_id', $job->id)->count())->toBe(0);
});
