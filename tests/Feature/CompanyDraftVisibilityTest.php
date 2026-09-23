<?php

use App\Models\Company;
use App\Models\UserQuery;
use App\Services\JobDiscoveryService;
use Illuminate\Support\Str;

test('unpublished company jobs are absent from every public read boundary', function (string $status) {
    $company = Company::create(['name' => '企業']);
    $attributes = ['occupation' => '機械設計', 'region' => '兵庫県', 'source_url' => 'https://careers.sample-company.jp/jobs/1'];
    $private = $company->jobPostings()->create([...$attributes, 'title' => '非公開の秘密求人', 'status' => $status]);
    $public = $company->jobPostings()->create([...$attributes, 'title' => '公開求人', 'status' => 'published', 'review_status' => 'not_submitted']);
    $query = UserQuery::create(['public_id' => (string) Str::uuid(), 'session_token' => 'visibility-token', 'raw_text' => '機械設計', 'occupation' => '機械設計', 'region' => '兵庫県']);
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token]);
    expect(app(JobDiscoveryService::class)->discover($query)->modelKeys())->toBe([$public->id]);
    foreach (['query.jobs', 'query.results'] as $route) {
        $this->get(route($route, ['userQuery' => $query->public_id]))->assertOk()->assertDontSee('非公開の秘密求人');
    }
    $this->get(route('query.jobs.show', ['userQuery' => $query->public_id, 'job' => $private->id]))->assertNotFound();
    $this->get(route('query.jobs.compare', ['userQuery' => $query->public_id, 'jobs' => [$private->id, $public->id]]))->assertNotFound();
    $this->get(route('routes.show', $private))->assertNotFound();
    $this->get(route('routes.action', $private))->assertNotFound();
    $this->get(route('query.jobs.show', ['userQuery' => $query->public_id, 'job' => $public->id]))->assertOk();
    $this->get(route('routes.show', $public))->assertOk();
    $this->get(route('routes.action', $public))->assertOk();
})->with(['draft', 'paused', 'closed']);
