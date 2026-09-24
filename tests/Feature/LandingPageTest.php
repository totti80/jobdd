<?php

use App\Models\Company;
use App\Models\JobPosting;
use Illuminate\Support\Facades\DB;

test('landing is a decision support page rather than the old input form', function () {
    $this->get('/')->assertOk()->assertSee('仕事の中身を知って、')->assertSee('JobDDは転職先を決めません。')
        ->assertSee('name="description"', false)->assertSee('id="new-jobs"', false)->assertSee('公開中の求人はまだありません')
        ->assertDontSee('<form', false)->assertDontSee('AIがおすすめ');
});

test('new jobs show only the latest six available published projections without authoring leaks', function () {
    $company = Company::create(['name' => '編集前企業']);
    $base = ['company_id' => $company->id, 'title' => '既存求人', 'occupation' => '機械設計', 'region' => '兵庫県', 'status' => 'published', 'published_at' => '2026-01-01'];
    for ($i = 1; $i <= 7; $i++) {
        JobPosting::create([...$base, 'title' => '既存求人'.$i, 'published_at' => '2026-01-0'.$i]);
    }
    foreach (['draft', 'paused', 'closed'] as $status) {
        JobPosting::create([...$base, 'status' => $status, 'title' => '非公開'.$status, 'published_at' => '2026-12-01']);
    }
    JobPosting::create([...$base, 'title' => '募集終了求人', 'unavailable_at' => now(), 'published_at' => '2026-12-01']);
    $unsupported = JobPosting::create([...$base, 'title' => '未対応版の秘密', 'published_at' => '2026-12-01']);
    $unsupported->publishedProfile()->create(['profile_data' => ['schema_version' => 999], 'published_at' => now()]);
    $snapshot = JobPosting::create([...$base, 'title' => '編集中の秘密', 'review_status' => 'pending_review', 'published_at' => '2026-12-01']);
    $snapshot->publishedProfile()->create(['profile_data' => ['schema_version' => 1, 'company' => ['name' => '承認済企業'], 'level_one' => ['title' => '承認済求人', 'occupation' => '機械設計', 'region' => '大阪府', 'salary_min' => 400, 'salary_max' => 600]], 'published_at' => '2026-01-04 12:00:00']);
    DB::enableQueryLog();
    $response = $this->get('/')->assertOk()->assertSeeInOrder(['既存求人7', '既存求人6', '既存求人5', '承認済求人', '既存求人4', '既存求人3']);
    foreach (['非公開draft', '非公開paused', '非公開closed', '募集終了求人', '未対応版の秘密', '編集中の秘密', '既存求人1', '既存求人2'] as $hidden) {
        $response->assertDontSee($hidden);
    }
    $response->assertSee('承認済企業')->assertSee('400〜600万円');
    expect(substr_count($response->getContent(), 'data-new-job='))->toBe(6);
    expect(collect(DB::getQueryLog())->filter(fn ($entry) => str_starts_with(strtolower($entry['query']), 'select'))->count())->toBeLessThanOrEqual(3);
    DB::disableQueryLog();
});

test('new jobs escape titles and use created date when publication date is absent', function () {
    $job = JobPosting::create(['company_id' => Company::create(['name' => '企業'])->id, 'title' => '<script>alert(1)</script>', 'status' => 'published']);
    $this->get('/')->assertOk()->assertSee($job->title)->assertDontSee($job->title, false)->assertSee('未確認');
});

test('homepage keeps editorial placeholders separate from public jobs and existing input', function () {
    $html = $this->get('/')->assertOk()->assertSeeInOrder([
        'id="home-title"', 'id="search-entry-title"', 'id="features-title"', 'id="preview-title"',
        'id="new-jobs"', 'id="pickup-title"', 'id="decision-title"', 'id="resources-title"', 'id="company-cta-title"',
    ], false)->assertDontSee('data-jobs-rail', false)->getContent();
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//section[@aria-labelledby="pickup-title"]//a')->length)->toBe(0);
    expect($xpath->query('//section[@aria-labelledby="resources-title"]//a')->length)->toBe(0);
    $entries = $xpath->query('//section[@aria-labelledby="search-entry-title"]//a');
    expect($entries->length)->toBe(4);
    foreach ($entries as $entry) {
        expect($entry->getAttribute('href'))->toBe(route('jobs.start'));
    }
});
