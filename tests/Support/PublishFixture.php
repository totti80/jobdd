<?php

use App\Models\Company;
use App\Models\User;

function publishFixture(string $role = 'company_owner'): array
{
    $user = User::factory()->create();
    $company = Company::create(['name' => '公開テスト株式会社']);
    $user->companies()->attach($company, ['role' => $role]);
    $job = $company->jobPostings()->create(['title' => '機械設計エンジニア', 'occupation' => '機械設計', 'region' => '兵庫県', 'salary_min' => 400, 'salary_max' => 600, 'description' => '機械の設計を担当します。', 'application_requirements' => '図面を読めること', 'employment_type' => '正社員', 'source_url' => 'https://careers.publish-company.jp/apply', 'status' => 'draft', 'review_status' => 'not_submitted']);
    $job->structuredProfile()->create(['design_target' => '生産設備', 'design_phases' => ['concept', 'detailed_design'], 'initial_assignment' => '部品設計', 'collaborators' => ['design_team', 'manufacturing'], 'customer_contact_frequency' => 'several_times_month', 'customer_contact_note' => '仕様の確認', 'manufacturing_relation_frequency' => 'almost_daily', 'manufacturing_relation_note' => '組立調整', 'site_relation_frequency' => 'rarely', 'site_relation_note' => '現場は別担当', 'work_style' => 'チームで設計', 'difficult_points' => '精度を保つ', 'product_context' => '工場向け', 'required_experience' => '設計経験', 'preferred_experience' => '解析経験', 'future_scope' => '構想', 'project_duration' => '半年', 'concurrent_projects' => '2案件', 'onboarding_challenges' => '製品知識', 'fit_work_style' => '相談しながら進める', 'misfit_work_style' => '単独完結を希望', 'representative_project' => ['what_made' => '搬送装置', 'phases' => ['concept'], 'duration' => '半年', 'team' => '3名', 'difficult_point' => '精度'], 'hard_to_convey' => '調整の多さ']);
    $job->toolUsages()->create(['tool_key' => 'autocad', 'tool_name' => 'AutoCAD', 'usage_context' => 'primary', 'experience_expectation' => 'required', 'usage_notes' => '製図']);
    $job->typicalDayItems()->create(['time_label' => '午前', 'activity' => '設計打合せ', 'sort_order' => 0]);

    return [$user, $job, User::factory()->create(['system_role' => 'platform_owner'])];
}
