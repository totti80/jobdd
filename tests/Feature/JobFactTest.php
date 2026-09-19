<?php

use App\Models\Company;
use App\Models\JobFact;
use App\Models\JobPosting;
use App\Models\Source;
use App\Models\UserQuery;
use Illuminate\Support\Carbon;

function makeJobFactPosting(): JobPosting
{
  $company = Company::create(['name' => 'JobFactテスト企業']);

  return JobPosting::create([
    'company_id' => $company->id,
    'title' => '機械設計テスト求人',
  ]);
}

test('JobPosting can have multiple facts and facts resolve their relations', function () {
  $jobPosting = makeJobFactPosting();
  $source = Source::create([
    'source_type' => 'official_site',
    'url' => 'https://example.test/jobs/1',
  ]);

  $firstFact = JobFact::create([
    'job_posting_id' => $jobPosting->id,
    'source_id' => $source->id,
    'fact_category' => 'skill',
    'fact_key' => 'cad',
    'fact_value' => 'AutoCAD',
    'normalized_value' => 'autocad',
    'extraction_method' => 'dictionary',
    'verification_status' => 'unverified',
    'evidence_text' => 'AutoCADの使用経験',
  ]);
  $secondFact = JobFact::create([
    'job_posting_id' => $jobPosting->id,
    'fact_category' => 'process',
    'fact_key' => 'design_process',
    'fact_value' => '基本設計',
    'extraction_method' => 'rule',
    'verification_status' => 'unverified',
  ]);

  expect($jobPosting->jobFacts()->count())->toBe(2)
    ->and($firstFact->jobPosting->is($jobPosting))->toBeTrue()
    ->and($firstFact->source->is($source))->toBeTrue()
    ->and($secondFact->source)->toBeNull();
});

test('deleting a source nulls the fact source and deleting a job cascades its facts', function () {
  $jobPosting = makeJobFactPosting();
  $source = Source::create([
    'source_type' => 'job_board',
    'url' => 'https://example.test/jobs/2',
  ]);
  $fact = JobFact::create([
    'job_posting_id' => $jobPosting->id,
    'source_id' => $source->id,
    'fact_category' => 'domain',
    'fact_key' => 'industry',
    'fact_value' => '産業機械',
    'extraction_method' => 'manual',
    'verification_status' => 'verified',
  ]);

  $source->delete();

  expect($fact->fresh()->source_id)->toBeNull();

  $jobPosting->delete();

  expect(JobFact::query()->find($fact->id))->toBeNull();
});

test('decision support casts and provider dates are preserved', function () {
  $query = UserQuery::create([
    'raw_text' => '機械設計を希望',
    'detailed_skills' => ['AutoCAD', '強度計算'],
    'priorities' => ['年収', '勤務地'],
  ]);
  $jobPosting = makeJobFactPosting();
  $publishedAt = Carbon::parse('2026-09-01 10:00:00');
  $providerUpdatedAt = Carbon::parse('2026-09-10 15:30:00');
  $jobPosting->update([
    'published_at' => $publishedAt,
    'provider_updated_at' => $providerUpdatedAt,
  ]);

  expect($query->fresh()->detailed_skills)->toBe(['AutoCAD', '強度計算'])
    ->and($query->fresh()->priorities)->toBe(['年収', '勤務地'])
    ->and($jobPosting->fresh()->published_at)->toBeInstanceOf(\DateTimeInterface::class)
    ->and($jobPosting->fresh()->provider_updated_at)->toBeInstanceOf(\DateTimeInterface::class)
    ->and($jobPosting->fresh()->published_at->equalTo($publishedAt))->toBeTrue()
    ->and($jobPosting->fresh()->provider_updated_at->equalTo($providerUpdatedAt))->toBeTrue();
});
