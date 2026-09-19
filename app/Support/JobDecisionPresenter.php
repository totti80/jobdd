<?php

namespace App\Support;

class JobDecisionPresenter
{
    public const STATUSES = ['match' => '確認できた', 'mismatch' => '条件と異なる', 'unknown' => '未確認'];

    public const BADGECLASSES = ['match' => 'bg-emerald-50 text-emerald-800 ring-emerald-200', 'mismatch' => 'bg-amber-50 text-amber-900 ring-amber-200', 'unknown' => 'bg-slate-100 text-slate-700 ring-slate-200'];

    public const LABELS = ['occupation' => '職種', 'region' => '勤務地', 'salary' => '年収', 'salary_min' => '掲載年収下限（万円）', 'salary_max' => '掲載年収上限（万円）', 'title' => '求人タイトル', 'description' => '求人本文'];

    public const ROLES = ['responsibility' => '担当業務として確認', 'required_experience' => '応募条件に記載', 'preferred_experience' => '歓迎条件に記載', 'collaboration' => '連携先として記載', 'other_department' => '他部署の業務として記載', 'company_context' => '会社・環境の説明', 'product_context' => '製品・サービスの説明', 'project_example' => '案件例・開発例', 'unknown' => '文脈未確認'];

    public const REASONS = [
        'same_occupation' => '希望職種と掲載職種が一致しています。',
        'different_occupation' => '掲載職種は希望職種と異なります。',
        'same_prefecture' => '掲載勤務地は希望地域と一致しています。',
        'different_prefecture' => '掲載勤務地は希望地域と異なります。',
        'salary_floor_meets' => '掲載年収の下限が希望額以上です。提示・採用時の年収を保証するものではありません。',
        'salary_ceiling_below' => '掲載年収の上限が希望額を下回っています。',
        'salary_overlap' => '掲載年収レンジが希望額をまたいでいるため、条件を満たすか断定できません。',
        'presence_not_found' => '保存済み求人情報では、このツールの使用を確認できません。',
        'context_not_responsibility' => 'この技術の記載はありますが、担当業務での使用は確認できません。',
        'action_unconfirmed' => '記載はありますが、本人が使用することまでは確認できません。',
        'confirmed_tool_use' => '担当業務でこのツールを使用する記載が確認できました。',
        'missing_job_value' => '保存済み求人情報に、比較に必要な情報がありません。',
        'invalid_job_value' => '掲載情報の形式や値を確認する必要があり、条件との一致を判断できません。',
        'unsupported_user_value' => 'この希望条件は現在の比較ルールでは確認できません。',
        'unsupported_salary_request' => '希望年収の範囲指定は現在の比較ルールでは確認できません。',
        'conflicting_job_evidence' => '保存された職種と求人本文に異なる情報があり、一致を断定できません。',
        'fact_unverified' => 'この記載は比較に用いる確認条件を満たしていません。',
        'evidence_missing' => '判断に必要な根拠や確認情報が不足しています。',
        'conflicting_contexts' => '複数の記載で文脈が異なるため、担当業務での使用を断定できません。',
        'context_unknown' => '記載の文脈を確認できず、担当業務での使用を判断できません。',
        'unsupported_tier2_requirement' => 'この条件は現在の比較ルールでは確認できません。',
    ];

    public static function safeUrl(mixed $url): ?string
    {
        return is_string($url) && filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
            ? $url : null;
    }
}
