<?php

namespace App\Support;

class StructuredJobOptions
{
    public const TITLES = [1 => '何を設計する仕事か', 2 => '使用ツールと経験', 3 => '誰と・どのように働くか', 4 => '仕事の進め方と難しさ', 5 => 'ある一日と代表的な案件'];

    public const PHASES = ['concept' => '構想', 'basic_design' => '基本設計', 'detailed_design' => '詳細設計', 'drafting' => '製図', 'analysis' => '解析', 'testing' => '試験・評価', 'manufacturing_support' => '製造対応', 'site_support' => '現地対応', 'other' => 'その他'];

    public const TOOLS = ['autocad' => 'AutoCAD', 'inventor' => 'Inventor', 'solidworks' => 'SolidWorks', 'catia' => 'CATIA', 'creo' => 'Creo', 'nx' => 'NX', 'electrical_cad' => '電気CAD', 'other_tool' => 'その他'];

    public const USAGES = ['primary' => '主に使う', 'occasional' => '時々使う', 'other_department' => '他部署・協力会社が使う', 'not_used' => '使用しない', 'undecided' => '未定'];

    public const EXPECTATIONS = ['required' => '必須経験', 'preferred' => '歓迎経験', 'not_required' => '経験不問', 'undecided' => '未定'];

    public const COLLABORATORS = ['design_team' => '同じ設計チーム', 'other_engineering' => '他分野設計者', 'manufacturing' => '製造', 'quality' => '品質', 'sales' => '営業', 'customer' => '顧客', 'partner_company' => '協力会社', 'site_staff' => '現場担当', 'other' => 'その他'];

    public const FREQUENCIES = ['almost_daily' => 'ほぼ毎日', 'several_times_week' => '週に数回', 'several_times_month' => '月に数回', 'rarely' => 'ほとんどない', 'depends_on_project' => '案件による', 'undecided' => '未定'];

    public const FIELDS = [
        1 => ['design_target' => '設計対象', 'product_context' => '製品の用途・背景', 'design_phases' => '担当工程', 'initial_assignment' => '最初に担当する仕事', 'future_scope' => '将来の担当範囲'],
        2 => ['required_experience' => '必要な経験', 'preferred_experience' => '歓迎する経験'],
        3 => ['collaborators' => '一緒に働く相手', 'customer_contact_frequency' => '顧客との接点の頻度', 'customer_contact_note' => '顧客との関わり方', 'manufacturing_relation_frequency' => '製造との接点の頻度', 'manufacturing_relation_note' => '製造との関わり方', 'site_relation_frequency' => '現場との接点の頻度', 'site_relation_note' => '現場との関わり方', 'work_style' => '仕事の進め方'],
        4 => ['project_duration' => '案件の期間', 'concurrent_projects' => '同時に担当する案件', 'difficult_points' => '仕事の難しさ', 'onboarding_challenges' => '入社後につまずきやすいこと', 'fit_work_style' => '取り組みやすい仕事のスタイル', 'misfit_work_style' => '負担になりやすい仕事のスタイル'],
        5 => ['representative_project' => '代表的な案件', 'hard_to_convey' => '求人票では伝わりにくいこと'],
    ];

    public static function options(string $field): ?array
    {
        return match ($field) {
            'design_phases', 'representative_project.phases' => self::PHASES,
            'collaborators' => self::COLLABORATORS,
            'tool_key' => self::TOOLS, 'usage_context' => self::USAGES,
            'experience_expectation' => self::EXPECTATIONS,
            'customer_contact_frequency', 'manufacturing_relation_frequency', 'site_relation_frequency' => self::FREQUENCIES,
            default => null,
        };
    }
}
