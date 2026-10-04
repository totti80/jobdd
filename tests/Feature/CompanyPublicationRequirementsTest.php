<?php

use App\Models\UserQuery;
use App\Services\CompanyJobAuthoringData;
use App\Services\CompanyJobPublishService;
use App\Services\CompanyJobPublishValidator;
use App\Services\CompanyJobReviewService;
use App\Services\StructuredProfileCompletionService;
use App\Support\CompanyJobPublicationRequirements;
use App\Support\StructuredJobOptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../Support/PublishFixture.php';

beforeEach(fn () => Mail::fake());

test('Level 1 alone can preview request approve and reach seeker detail and comparison', function () {
    [$owner, $job, $admin] = publishFixture();
    $job->structuredProfile()->delete();
    $job->toolUsages()->delete();
    $job->typicalDayItems()->delete();
    $completion = app(StructuredProfileCompletionService::class)->calculate($job->fresh());
    expect($completion['percentage'])->toBe(38);
    $response = $this->actingAs($owner)->get(route('company.jobs.preview', $job))->assertOk();
    expect($response->viewData('publishErrors'))->toBe([]);
    $response->assertDontSee('公開申請に必要な入力')->assertSee('入力充足率が100%である必要はありません');
    $this->post(route('company.jobs.review-request', $job))->assertSessionHasNoErrors();
    expect($job->fresh()->review_status)->toBe('pending_review')->and($job->fresh()->status)->toBe('draft');
    app(CompanyJobPublishService::class)->approve($job->fresh(), $admin, app(CompanyJobAuthoringData::class)->token($job->fresh()));
    expect($job->fresh()->status)->toBe('published')
        ->and($job->publishedProfile()->first()->profile_data['structured_profile'])->toBe([])
        ->and($job->publishedProfile()->first()->profile_data['tool_usages'])->toBe([])
        ->and($job->publishedProfile()->first()->profile_data['typical_day_items'])->toBe([])
        ->and($job->jobFacts()->where('extraction_method', 'company_self_reported')->count())->toBe(0);
    [$otherOwner, $other, $otherAdmin] = publishFixture();
    app(CompanyJobReviewService::class)->request($other->fresh(), $otherOwner);
    app(CompanyJobPublishService::class)->approve($other->fresh(), $otherAdmin, app(CompanyJobAuthoringData::class)->token($other->fresh()));
    $query = UserQuery::create(['public_id' => (string) Str::uuid(), 'session_token' => 'publication-test', 'raw_text' => 'fixture', 'occupation' => '機械設計', 'region' => '兵庫県']);
    $this->withSession(['jobdd_query_token_'.$query->public_id => $query->session_token]);
    $this->get(route('query.jobs.show', ['userQuery' => $query->public_id, 'job' => $job->id]))->assertOk()->assertSee('この情報はまだ確認できていません');
    $this->get(route('query.jobs.compare', ['userQuery' => $query->public_id, 'jobs' => [$job->id, $other->id]]))->assertOk();
});

test('every optional profile field can be empty when requesting publication', function (string $field) {
    [$owner, $job] = publishFixture();
    $job->structuredProfile()->update([$field => null]);
    expect(app(CompanyJobPublishValidator::class)->errors($job->fresh()))->toBe([]);
    $this->actingAs($owner)->post(route('company.jobs.review-request', $job))->assertSessionHasNoErrors();
})->with(array_merge(...array_map('array_keys', StructuredJobOptions::FIELDS)));

test('invalid optional input is rejected consistently in preview request and approval', function (string $target, array $attributes) {
    [$owner, $job, $admin] = publishFixture();
    match ($target) {
        'profile' => $job->structuredProfile()->first()->update($attributes),
        'tool' => $job->toolUsages()->first()->update($attributes),
        'day' => $job->typicalDayItems()->first()->update($attributes),
    };
    $errors = app(CompanyJobPublishValidator::class)->errors($job->fresh());
    expect($errors)->not->toBe([]);
    $preview = $this->actingAs($owner)->get(route('company.jobs.preview', $job))->assertOk();
    expect($preview->viewData('publishErrors'))->toBe($errors);
    $this->post(route('company.jobs.review-request', $job))->assertSessionHasErrors();
    expect($job->fresh()->review_status)->toBe('not_submitted');
    Mail::assertNothingSent();
    $job->update(['review_status' => 'pending_review']);
    try {
        app(CompanyJobPublishService::class)->approve($job->fresh(), $admin, app(CompanyJobAuthoringData::class)->token($job->fresh()));
        $this->fail('Invalid optional data must not be approved.');
    } catch (ValidationException $exception) {
        expect($exception->validator->errors()->all())->toBe($errors);
    }
    expect($job->publishedProfile()->exists())->toBeFalse()->and($job->jobFacts()->count())->toBe(0);
})->with([
    ['profile', ['design_phases' => ['invalid']]],
    ['profile', ['design_phases' => ['concept', 'concept']]],
    ['profile', ['design_phases' => 'invalid']],
    ['profile', ['collaborators' => ['invalid']]],
    ['profile', ['customer_contact_frequency' => 'invalid']],
    ['profile', ['manufacturing_relation_frequency' => 'invalid']],
    ['profile', ['site_relation_frequency' => 'invalid']],
    ['profile', ['product_context' => str_repeat('x', 10001)]],
    ['profile', ['work_style' => str_repeat('x', 10001)]],
    ['profile', ['representative_project' => ['phases' => ['invalid']]]],
    ['profile', ['representative_project' => ['unknown' => 'invalid']]],
    ['tool', ['tool_name' => null]],
    ['tool', ['usage_context' => null]],
    ['tool', ['experience_expectation' => null]],
    ['tool', ['tool_key' => 'invalid']],
    ['tool', ['usage_context' => 'invalid']],
    ['tool', ['experience_expectation' => 'invalid']],
    ['tool', ['usage_notes' => str_repeat('x', 10001)]],
    ['day', ['time_label' => null]],
    ['day', ['activity' => null]],
]);

test('complete optional rows do not hide incomplete additional rows', function (string $relation, array $row) {
    [$owner, $job] = publishFixture();
    $job->$relation()->create($row);
    $this->actingAs($owner)->post(route('company.jobs.review-request', $job))->assertSessionHasErrors();
})->with([
    ['toolUsages', ['tool_name' => 'Additional CAD']],
    ['typicalDayItems', ['time_label' => '午後']],
]);

test('publication badges match shared rules while draft saving stays permissive', function () {
    [$owner, $job] = publishFixture();
    $this->actingAs($owner);
    $basic = $this->get(route('company.jobs.basic.edit', $job))->assertOk();
    $doc = new DOMDocument;
    @$doc->loadHTML('<?xml encoding="UTF-8">'.$basic->getContent());
    $xpath = new DOMXPath($doc);
    foreach (CompanyJobAuthoringData::BASIC_FIELDS as $field) {
        $label = $xpath->query('//label[@for="'.$field.'"]')->item(0);
        expect($label)->not->toBeNull()
            ->and($label->textContent)->toContain(CompanyJobPublicationRequirements::requirement('level_one.'.$field));
    }
    expect($xpath->query('//*[@required]')->length)->toBe(1);
    foreach (StructuredJobOptions::FIELDS as $step => $fields) {
        $response = $this->get(route('company.jobs.structured.edit', [$job, $step]))->assertOk();
        @$doc->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new DOMXPath($doc);
        expect($xpath->query('//*[@required]')->length)->toBe(0);
        foreach ($fields as $field => $label) {
            expect(CompanyJobPublicationRequirements::requirement('structured_profile.'.$field))->toBe('任意');
            $node = match ($field) {
                'design_phases', 'collaborators' => $xpath->query('//form//legend[contains(., "'.$label.'")]')->item(0),
                'representative_project' => $xpath->query('//form//h2[contains(., "'.$label.'")]')->item(0),
                default => $xpath->query('//form//label[@for="'.$field.'"]')->item(0),
            };
            expect($node)->not->toBeNull()->and($node->textContent)->toContain('任意');
        }
        $response->assertSee('公開申請の任意項目')->assertSee('100%未満でも');
    }
    $this->patch(route('company.jobs.basic.update', $job), ['title' => '途中保存'])->assertSessionHasNoErrors();
    $this->patch(route('company.jobs.structured.update', [$job, 2]), ['tools' => [['tool_name' => '途中のTool']]])->assertSessionHasNoErrors();
    expect(app(CompanyJobPublishValidator::class)->errors($job->fresh()))->not->toBe([]);
});

test('company publication browser fixtures export real form and preview responses', function () {
    $capture = getenv('JOBDD_PUBLICATION_CAPTURE_DIR');
    [$owner, $job] = publishFixture();
    $job->structuredProfile()->delete();
    $job->toolUsages()->delete();
    $job->typicalDayItems()->delete();
    $this->actingAs($owner);
    if ($capture && ! is_dir($capture)) {
        mkdir($capture, 0777, true);
    }
    $urls = ['basic' => route('company.jobs.basic.edit', $job), 'preview' => route('company.jobs.preview', $job), 'dashboard' => route('company.dashboard')];
    foreach (range(1, 5) as $step) {
        $urls['step-'.$step] = route('company.jobs.structured.edit', [$job, $step]);
    }
    foreach ($urls as $name => $url) {
        $response = $this->get($url)->assertOk();
        if ($capture) {
            file_put_contents($capture.'/'.$name.'.html', $response->getContent());
        }
    }
});
