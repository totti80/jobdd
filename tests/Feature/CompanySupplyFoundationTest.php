<?php

use App\Models\Company;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('ownership is many to many with explicit roles and timestamps', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $company = Company::create(['name' => '企業A']);
    $other = Company::create(['name' => '企業B']);
    $owner->companies()->attach($company, ['role' => 'company_owner']);
    $owner->companies()->attach($other, ['role' => 'company_editor']);
    $company->users()->attach($editor, ['role' => 'company_editor']);

    expect($owner->companies)->toHaveCount(2)
        ->and($company->users)->toHaveCount(2)
        ->and($owner->companies->find($company->id)->pivot->role)->toBe('company_owner')
        ->and($company->users->find($editor->id)->pivot->role)->toBe('company_editor')
        ->and($owner->companies->first()->pivot->created_at)->toBeInstanceOf(DateTimeInterface::class)
        ->and($owner->fresh()->system_role)->toBe('user');

    $owner->system_role = 'platform_owner';
    $owner->save();
    expect($owner->fresh()->system_role)->toBe('platform_owner');

    $owner->delete();
    expect($company->users()->count())->toBe(1);
    $company->delete();
    expect(DB::table('company_user')->count())->toBe(0);
});

test('ownership rejects duplicate memberships', function () {
    $user = User::factory()->create();
    $company = Company::create(['name' => '企業']);
    $user->companies()->attach($company, ['role' => 'company_owner']);
    expect(fn () => $user->companies()->attach($company, ['role' => 'company_editor']))
        ->toThrow(QueryException::class);
});

test('authoring relations allow incomplete drafts and round trip JSON independently of snapshots', function () {
    $company = Company::create(['name' => '企業']);
    $job = $company->jobPostings()->create(['title' => '設計', 'status' => 'draft']);
    $profile = $job->structuredProfile()->create([]);
    $tool = $job->toolUsages()->create([]);
    $day = $job->typicalDayItems()->create([]);
    expect($profile->fresh()->design_target)->toBeNull()
        ->and($tool->fresh()->usage_context)->toBeNull()
        ->and($day->fresh()->activity)->toBeNull();

    $profile->update([
        'design_target' => '設備',
        'design_phases' => ['basic_design', 'detail_design'],
        'collaborators' => ['manufacturing'],
        'representative_project' => ['title' => '装置開発', 'details' => ['期間' => '半年']],
    ]);
    $tool->update(['tool_name' => 'AutoCAD', 'usage_context' => 'occasional', 'experience_expectation' => 'not_required']);
    $day->update(['time_label' => '午前', 'activity' => '設計']);
    $job->toolUsages()->create(['tool_name' => 'SolidWorks', 'sort_order' => 2]);
    $job->typicalDayItems()->create(['time_label' => '案件により随時', 'activity' => '打合せ', 'sort_order' => 2]);
    $snapshot = $job->publishedProfile()->create([
        'profile_data' => ['design_target' => '公開済み設備', 'tools' => [['name' => 'AutoCAD']]],
        'published_at' => '2026-09-23 09:00:00',
    ]);
    $profile->update(['design_target' => '編集中']);

    expect($job->fresh()->status)->toBe('draft')
        ->and($job->structuredProfile->is($profile))->toBeTrue()
        ->and($job->toolUsages)->toHaveCount(2)
        ->and($job->typicalDayItems)->toHaveCount(2)
        ->and($job->publishedProfile->is($snapshot))->toBeTrue()
        ->and($profile->fresh()->design_phases)->toBe(['basic_design', 'detail_design'])
        ->and($profile->fresh()->collaborators)->toBe(['manufacturing'])
        ->and($profile->fresh()->representative_project)->toEqual(['title' => '装置開発', 'details' => ['期間' => '半年']])
        ->and($tool->fresh()->sort_order)->toBe(0)
        ->and($day->fresh()->sort_order)->toBe(0)
        ->and($day->fresh()->time_label)->toBe('午前')
        ->and($snapshot->fresh()->profile_data)->toEqual(['design_target' => '公開済み設備', 'tools' => [['name' => 'AutoCAD']]])
        ->and($snapshot->fresh()->published_at->format('Y-m-d H:i:s'))->toBe('2026-09-23 09:00:00');

    foreach ([$profile, $tool, $day, $snapshot] as $child) {
        expect($child->jobPosting->is($job))->toBeTrue();
    }
    $job->delete();
    foreach ([$profile, $tool, $day, $snapshot] as $child) {
        expect($child->fresh())->toBeNull();
    }
});

test('one to one profiles enforce uniqueness', function (string $relation, array $attributes) {
    $company = Company::create(['name' => '企業']);
    $job = $company->jobPostings()->create(['title' => '設計']);
    $job->$relation()->create($attributes);
    expect(fn () => $job->$relation()->create($attributes))->toThrow(QueryException::class);
})->with([
    ['structuredProfile', []],
    ['publishedProfile', ['profile_data' => []]],
]);

test('new child tables reject missing parents', function (string $table, array $attributes) {
    expect(fn () => DB::table($table)->insert($attributes))->toThrow(QueryException::class);
})->with([
    ['company_user', ['company_id' => 999999, 'user_id' => 999999, 'role' => 'company_owner']],
    ['job_structured_profiles', ['job_posting_id' => 999999]],
    ['job_tool_usages', ['job_posting_id' => 999999]],
    ['job_typical_day_items', ['job_posting_id' => 999999]],
    ['job_published_profiles', ['job_posting_id' => 999999, 'profile_data' => '{}']],
]);

test('status defaults preserve SQL inserts and Eloquent importer creation paths', function () {
    $company = Company::create(['name' => '企業']);
    $sqlId = DB::table('job_postings')->insertGetId(['company_id' => $company->id, 'title' => 'SQL']);
    $created = JobPosting::create(['company_id' => $company->id, 'title' => 'create']);
    $upserted = JobPosting::updateOrCreate(['company_id' => $company->id, 'title' => 'upsert']);
    $filled = new JobPosting;
    $filled->fill(['company_id' => $company->id, 'title' => 'fill'])->save();
    foreach ([$sqlId, $created->id, $upserted->id, $filled->id] as $id) {
        expect(JobPosting::findOrFail($id)->status)->toBe('published');
    }
    JobPosting::updateOrCreate(['id' => $upserted->id], ['description' => '更新']);
    expect($upserted->fresh()->status)->toBe('published');
});

test('context role accepts explicit company values and preserves null external facts', function () {
    $company = Company::create(['name' => '企業']);
    $job = $company->jobPostings()->create(['title' => '設計']);
    $attributes = ['fact_category' => 'job_content', 'fact_key' => 'design_target', 'fact_value' => '設備',
        'extraction_method' => 'rule', 'verification_status' => 'unverified', 'observed_at' => '2026-09-23 09:00:00'];
    $external = $job->jobFacts()->create($attributes);
    $companyFact = $job->jobFacts()->create([...$attributes, 'context_role' => 'responsibility']);
    expect($external->fresh()->context_role)->toBeNull()
        ->and($companyFact->fresh()->context_role)->toBe('responsibility')
        ->and($companyFact->fresh()->observed_at)->toBeInstanceOf(DateTimeInterface::class)
        ->and($companyFact->jobPosting->is($job))->toBeTrue();
});
