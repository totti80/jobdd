<?php

namespace App\Support;

class LegacyJobInformation
{
    public function present(array $presence): array
    {
        $fields = ['設計対象' => [], '担当工程' => [], 'CAD / Tool' => [], '関係者' => []];
        foreach ($presence as $entry) {
            $fact = $entry['fact'];
            $field = match ($fact->fact_category) {
                'job_content' => $fact->fact_key === 'design_target' ? '設計対象' : null,
                'process_stage', 'design_phase' => '担当工程',
                'tool', 'tool_system', 'tool_usage', 'tool_expectation' => 'CAD / Tool',
                'collaboration' => '関係者',
                default => null,
            };
            if ($field === null) {
                continue;
            }
            $value = $fact->normalized_value ?: $fact->fact_value;
            if (! is_string($value) || trim($value) === '') {
                continue;
            }
            $fields[$field][] = $value.'（'.(JobDecisionPresenter::ROLES[$entry['context']['role']] ?? '文脈未確認').'）';
        }

        return array_map(fn ($values) => $values ? implode('・', array_unique($values)) : '未確認', $fields)
            + ['Typical Day' => '未確認'];
    }
}
