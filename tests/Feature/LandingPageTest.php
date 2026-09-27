<?php

use App\Models\Company;
use App\Models\JobPosting;
use Illuminate\Support\Facades\DB;

test('landing supports decisions with the shared compact entry form', function () {
    $this->get('/')->assertOk()->assertSee('求人を探すだけでは、')->assertSee('わからない。')->assertSee('求人候補を見る')
        ->assertSee('name="description"', false)->assertSee('id="new-jobs"', false)->assertSee('公開中の求人はまだありません')
        ->assertSee('action="'.route('jobs.store').'"', false)->assertDontSee('AIがおすすめ');
});

test('new jobs show only the latest ten available published projections without authoring leaks', function () {
    $company = Company::create(['name' => '編集前企業']);
    $base = ['company_id' => $company->id, 'title' => '既存求人', 'occupation' => '機械設計', 'region' => '兵庫県', 'status' => 'published', 'published_at' => '2026-01-01'];
    for ($i = 1; $i <= 11; $i++) {
        JobPosting::create([...$base, 'title' => '既存求人'.sprintf('%02d', $i), 'published_at' => sprintf('2026-01-%02d', $i)]);
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
    $response = $this->get('/')->assertOk()->assertSeeInOrder(['既存求人11', '既存求人10', '既存求人09', '既存求人08', '既存求人07', '既存求人06', '既存求人05', '承認済求人', '既存求人04', '既存求人03']);
    foreach (['非公開draft', '非公開paused', '非公開closed', '募集終了求人', '未対応版の秘密', '編集中の秘密', '既存求人01', '既存求人02'] as $hidden) {
        $response->assertDontSee($hidden);
    }
    $response->assertSee('承認済企業')->assertSee('400〜600万円');
    expect(substr_count($response->getContent(), 'data-new-job='))->toBe(10);
    expect(collect(DB::getQueryLog())->filter(fn ($entry) => str_starts_with(strtolower($entry['query']), 'select'))->count())->toBeLessThanOrEqual(3);
    DB::disableQueryLog();
});

test('new jobs escape titles and use created date when publication date is absent', function () {
    $job = JobPosting::create(['company_id' => Company::create(['name' => '企業'])->id, 'title' => '<script>alert(1)</script>', 'status' => 'published']);
    $this->get('/')->assertOk()->assertSee($job->title)->assertDontSee($job->title, false)->assertSee('未確認');
});

test('homepage presents final sections and removes redundant editorial samples', function () {
    $html = $this->get('/')->assertOk()->assertSeeInOrder([
        'id="home-title"', 'id="features-title"', 'id="views-title"', 'id="preview-title"',
        'id="entry-form"', 'id="new-jobs"', 'id="resources-title"', 'id="company-cta-title"',
    ], false)->assertDontSee('data-jobs-rail', false)->getContent();
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//section[@aria-labelledby="features-title"]//article')->length)->toBe(6);
    expect($xpath->query('//section[@aria-labelledby="views-title"]//article')->length)->toBe(3);
    expect($xpath->query('//section[@aria-labelledby="resources-title"]//article')->length)->toBe(3);
    expect($xpath->query('//section[@aria-labelledby="resources-title"]//a')->length)->toBe(0);
    expect($xpath->query('//section[@aria-labelledby="preview-title"]//table')->length)->toBe(0);
    expect($xpath->query('//dl[@class="home-normalized-facts"]/div')->length)->toBe(7);
    expect($xpath->query('//h1')->length)->toBe(1);
    foreach (['search-entry-title', 'pickup-title', 'home-decision-steps', 'home-sample', 'decision-title', '説明用サンプル', '求人A', '求人B', 'AIがおすすめ', '最適求人', 'No.1', '総合ランキング'] as $removed) {
        expect($html)->not->toContain($removed);
    }
    foreach (['希望条件を入力する', '近畿6府県 × 機械設計・電気設計 専門', '卒業制作版', '求人票の記載例', 'JobDDで整理した表示例', 'jobdd-hero-kinki.png'] as $copy) {
        expect($html)->toContain($copy);
    }
});

test('carousel has native keyboard region unique job links and explicit animation controls', function () {
    JobPosting::create(['company_id' => Company::create(['name' => '企業'])->id, 'title' => str_repeat('長い機械設計の求人名', 20), 'status' => 'published']);
    $html = $this->get('/')->assertOk()->getContent();
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//*[@data-jobs-rail and @tabindex="0" and @role="region" and @aria-describedby="new-jobs-help"]')->length)->toBe(1);
    expect($xpath->query('//button[@data-rail-toggle and @aria-pressed="false" and @aria-controls="new-jobs-rail"]')->length)->toBe(1);
    expect($xpath->query('//*[@data-new-job]//a')->length)->toBe(1);
    expect($xpath->query('//*[@data-rail-copy]')->length)->toBe(0);
});

test('home and start share every field option and the existing POST contract', function () {
    $contracts = [];
    foreach (['home', 'jobs.start'] as $route) {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$this->get(route($route))->assertOk()->getContent());
        $xpath = new DOMXPath($dom);
        $form = $xpath->query('//form[@id="entry-form"]')->item(0);
        expect($form->getAttribute('method'))->toBe('POST');
        expect($form->getAttribute('action'))->toBe(route('jobs.store'));
        $fields = [];
        foreach ($xpath->query('//form[@id="entry-form"]//input | //form[@id="entry-form"]//select | //form[@id="entry-form"]//option | //form[@id="entry-form"]//textarea') as $field) {
            if ($field->getAttribute('name') === '_token') {
                expect($field->getAttribute('value'))->not->toBe('');

                continue;
            }
            $fields[] = $dom->saveHTML($field);
        }
        $contracts[] = $fields;
    }
    expect($contracts[0])->toBe($contracts[1]);
});

test('review removes overlays notes and only the three requested CTA arrows', function () {
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$this->get('/')->assertOk()->getContent());
    $xpath = new DOMXPath($dom);
    foreach ($xpath->query('//section[@aria-labelledby="home-title"]//a | //section[@aria-labelledby="company-cta-title"]//a') as $cta) {
        expect($cta->textContent)->not->toContain('→');
    }
    expect($xpath->query('//section[@aria-labelledby="features-title"]//article[contains(@class,"home-feature-large")]')->length)->toBe(6);
    expect($xpath->query('//section[@aria-labelledby="views-title"]//article[contains(@class,"home-feature-large")]')->length)->toBe(3);
    expect($xpath->query('//*[@id="decision-title"]')->length)->toBe(0);
    expect($xpath->query('//form[@id="entry-form"]')->length)->toBe(1);
});
