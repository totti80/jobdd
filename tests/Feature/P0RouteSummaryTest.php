<?php

use App\Models\UserQuery;
use App\Models\Agency;
use App\Models\ApplicationRoute;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\Platform;
use App\Services\RouteSummaryService;
use App\Services\OccupationNormalizer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('結果画面は候補がなくても3経路を表示する', function () {
  $response = $this->post(route('query.store'), [
    'raw_text' => '希望職種は機械設計です。希望勤務地は兵庫県です。',
    'occupation' => '機械設計',
    'prefecture' => '兵庫県',
  ]);

  $response->assertRedirect();

  $this->get($response->headers->get('Location'))
    ->assertOk()
    ->assertSee('Direct')
    ->assertSee('Agent')
    ->assertSee('Platform')
    ->assertSee('現在確認できるEvidenceがありません')
    ->assertSee('求人が存在しないことを意味しません');
});

test('user queryはセッション所有者以外から公開IDで参照できない', function () {
  $query = UserQuery::create([
    'public_id' => (string) Str::uuid(),
    'session_token' => Str::random(64),
    'raw_text' => '希望職種は機械設計です。',
    'occupation' => '機械設計',
  ]);

  $this->get(route('query.results', ['userQuery' => $query->public_id]))
    ->assertNotFound();
});

function makeRouteSummaryJob(array $attributes = []): JobPosting
{
  $companyName = $attributes['company_name'] ?? '確認企業';
  unset($attributes['company_name']);

  $company = Company::create([
    'name' => $companyName,
  ]);

  return JobPosting::create(array_merge([
    'company_id' => $company->id,
    'title' => '機械設計担当',
    'occupation' => '機械設計',
    'region' => '兵庫県神戸市',
    'salary_min' => 500,
    'salary_max' => 700,
    'description' => null,
  ], $attributes));
}

test('ソフトウェア求人、低年収、ダミー企業、失効routeを候補から除外する', function () {
  $platform = Platform::create(['name' => '確認媒体']);

  $softwareJob = makeRouteSummaryJob([
    'title' => '画像処理・データ解析ソリューション開発担当者/マネージャー',
    'occupation' => 'ソフトウェア・IT',
  ]);
  $lowSalaryJob = makeRouteSummaryJob(['salary_min' => 300, 'salary_max' => null]);
  $dummyJob = makeRouteSummaryJob(['company_name' => 'A製作所']);
  $unavailableJob = makeRouteSummaryJob();
  $validJob = makeRouteSummaryJob(['title' => '機械設計リーダー']);

  foreach ([$softwareJob, $lowSalaryJob, $dummyJob, $unavailableJob, $validJob] as $job) {
    ApplicationRoute::create([
      'job_posting_id' => $job->id,
      'route_type' => 'platform',
      'platform_id' => $platform->id,
      'application_url' => 'https://example.test/jobs/' . $job->id,
      'availability_status' => $job->id === $unavailableJob->id ? 'unavailable' : 'available',
      'unavailable_at' => $job->id === $unavailableJob->id ? now() : null,
    ]);
  }

  ApplicationRoute::create([
    'job_posting_id' => $validJob->id,
    'route_type' => 'platform',
    'platform_id' => $platform->id,
    'application_url' => 'https://example.test/jobs/' . $validJob->id . '?duplicate=1',
    'availability_status' => 'available',
  ]);

  $query = UserQuery::create([
    'raw_text' => '機械設計 兵庫県 400万円以上',
    'occupation' => '機械設計',
    'region' => '兵庫県',
    'salary_min' => 400,
  ]);

  $summary = app(RouteSummaryService::class)->summarize($query)->firstWhere('route_type', 'platform');

  expect($summary['candidate_count'])->toBe(1)
    ->and($summary['evidence_count'])->toBe(1)
    ->and($summary['representative_candidates'])->toHaveCount(1)
    ->and($summary['representative_candidates']->first()['title'])->toBe('機械設計リーダー');
});

test('画像処理・データ解析求人はソフトウェア職種として正規化される', function () {
  expect(app(OccupationNormalizer::class)->normalize(
    '画像処理・データ解析ソリューション開発担当者/マネージャー'
  ))->toBe('ソフトウェア・IT');
});

test('Meitec importerを2回実行してもrouteが増えない', function () {
  $jsonPath = storage_path('app/private/crawler/meitec_next_jobs.json');

  if (!file_exists($jsonPath)) {
    $this->markTestSkipped('Meitec crawler JSON is not available.');
  }

  DB::table('agencies')->insert([
    'id' => 7,
    'name' => 'メイテックネクスト',
    'job_count' => 0,
    'evidence_level' => 'medium',
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  Artisan::call('crawler:import-meitec-next-jobs');
  $firstCount = ApplicationRoute::where('provider_key', 'meitec_next')->count();

  Artisan::call('crawler:import-meitec-next-jobs');
  $secondCount = ApplicationRoute::where('provider_key', 'meitec_next')->count();

  expect($firstCount)->toBeGreaterThan(0)
    ->and($secondCount)->toBe($firstCount);
});
