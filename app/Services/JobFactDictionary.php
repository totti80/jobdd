<?php

namespace App\Services;

class JobFactDictionary
{
  /**
   * Aliases are ordered from the most specific spelling to the shortest one.
   */
  public static function definitions(): array
  {
    return [
      [
        'fact_category' => 'tool',
        'fact_key' => 'autocad',
        'normalized_value' => 'AutoCAD',
        'aliases' => ['Auto CAD', 'AutoCAD'],
      ],
      [
        'fact_category' => 'tool',
        'fact_key' => 'inventor',
        'normalized_value' => 'Inventor',
        'aliases' => ['Autodesk Inventor', 'Inventor'],
      ],
      [
        'fact_category' => 'tool',
        'fact_key' => 'solidworks',
        'normalized_value' => 'SolidWorks',
        'aliases' => ['Solid Works', 'SolidWorks'],
      ],
      [
        'fact_category' => 'tool',
        'fact_key' => 'catia',
        'normalized_value' => 'CATIA',
        'aliases' => ['CATIA V5', 'CATIA V6', 'CATIA'],
      ],
      [
        'fact_category' => 'tool',
        'fact_key' => 'creo',
        'normalized_value' => 'Creo',
        'aliases' => ['Creo Parametric', 'Creo'],
      ],
      [
        'fact_category' => 'tool',
        'fact_key' => 'nx',
        'normalized_value' => 'NX',
        'aliases' => ['Siemens NX', 'NX'],
      ],
      [
        'fact_category' => 'analysis',
        'fact_key' => 'fem',
        'normalized_value' => 'FEM',
        'aliases' => ['有限要素解析', '有限要素法', 'FEM'],
      ],
      [
        'fact_category' => 'analysis',
        'fact_key' => 'cae',
        'normalized_value' => 'CAE',
        'aliases' => ['CAE'],
      ],
      [
        'fact_category' => 'design_specialty',
        'fact_key' => 'strength_calculation',
        'normalized_value' => '強度計算',
        'aliases' => ['強度計算'],
      ],
      [
        'fact_category' => 'design_specialty',
        'fact_key' => 'mechanism_design',
        'normalized_value' => '機構設計',
        'aliases' => ['機構設計'],
      ],
      [
        'fact_category' => 'design_specialty',
        'fact_key' => 'piping_design',
        'normalized_value' => '配管設計',
        'aliases' => ['配管設計'],
      ],
      [
        'fact_category' => 'process_stage',
        'fact_key' => 'concept_design',
        'normalized_value' => '構想設計',
        'aliases' => ['構想設計'],
      ],
      [
        'fact_category' => 'process_stage',
        'fact_key' => 'basic_design',
        'normalized_value' => '基本設計',
        'aliases' => ['基本設計'],
      ],
      [
        'fact_category' => 'process_stage',
        'fact_key' => 'detail_design',
        'normalized_value' => '詳細設計',
        'aliases' => ['詳細設計'],
      ],
      [
        'fact_category' => 'tool_system',
        'fact_key' => 'plc',
        'normalized_value' => 'PLC',
        'aliases' => ['ラダー制御', 'シーケンサー', 'シーケンサ', 'PLC'],
      ],
      [
        'fact_category' => 'tool_system',
        'fact_key' => 'electrical_cad',
        'normalized_value' => '電気CAD',
        'aliases' => ['電気CAD', 'E-CAD', 'ECAD'],
      ],
      [
        'fact_category' => 'design_specialty',
        'fact_key' => 'control_panel',
        'normalized_value' => '制御盤',
        'aliases' => ['制御盤'],
      ],
      [
        'fact_category' => 'design_specialty',
        'fact_key' => 'power_distribution',
        'normalized_value' => '受配電',
        'aliases' => ['受配電'],
      ],
      [
        'fact_category' => 'design_specialty',
        'fact_key' => 'sequence_control',
        'normalized_value' => 'シーケンス',
        'aliases' => ['シーケンス'],
      ],
      [
        'fact_category' => 'design_specialty',
        'fact_key' => 'circuit_design',
        'normalized_value' => '回路設計',
        'aliases' => ['回路設計'],
      ],
      [
        'fact_category' => 'design_specialty',
        'fact_key' => 'control_design',
        'normalized_value' => '制御設計',
        'aliases' => ['制御設計'],
      ],
      [
        'fact_category' => 'design_specialty',
        'fact_key' => 'fa',
        'normalized_value' => 'FA',
        'aliases' => ['FA'],
      ],
      [
        'fact_category' => 'design_specialty',
        'fact_key' => 'facility_design',
        'normalized_value' => '設備設計',
        'aliases' => ['設備設計'],
      ],
    ];
  }

  /** Company authoring definitions are separate from the unchanged legacy extraction dictionary. */
  public static function companyDefinitions(): array
  {
    return [
      'job_content' => ['design_target'=>'responsibility','product_context'=>'product_context','hard_to_convey'=>'company_context'],
      'design_phase' => array_fill_keys(array_keys(\App\Support\StructuredJobOptions::PHASES), 'responsibility'),
      'assignment' => ['initial_assignment'=>'responsibility','future_scope'=>'responsibility'],
      'tool_usage' => array_fill_keys(array_keys(\App\Support\StructuredJobOptions::TOOLS), 'unknown'),
      'tool_expectation' => array_fill_keys(array_keys(\App\Support\StructuredJobOptions::TOOLS), 'unknown'),
      'experience' => ['required_experience'=>'required_experience','preferred_experience'=>'preferred_experience'],
      'collaboration' => array_fill_keys([...array_keys(\App\Support\StructuredJobOptions::COLLABORATORS),'customer_contact','manufacturing_relation','site_relation'], 'collaboration'),
      'work_style' => ['work_style'=>'company_context','fit_work_style'=>'company_context','misfit_work_style'=>'company_context'],
      'work_reality' => ['project_duration'=>'company_context','concurrent_projects'=>'company_context','difficult_points'=>'company_context','onboarding_challenges'=>'company_context'],
      'project_example' => ['representative_project'=>'project_example'],
    ];
  }

  public static function companyRole(string $category, string $key, string $value): string
  {
    $role = self::companyDefinitions()[$category][$key] ?? throw new \InvalidArgumentException('Unknown company Fact definition.');
    return match ($category) {
      'tool_usage' => match ($value) { 'primary','occasional'=>'responsibility', 'other_department'=>'other_department', default=>'unknown' },
      'tool_expectation' => match ($value) { 'required'=>'required_experience','preferred'=>'preferred_experience',default=>'unknown' },
      default => $role,
    };
  }
}
