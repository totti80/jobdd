<?php

namespace App\Support;

use App\Models\JobPublishedProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Published display material only; no Fit evaluation or authoring reads. */
class JobComparisonPresenter
{
    public function present(array $item, ?JobPublishedProfile $snapshot, Collection $routes): array
    {
        $profile = $snapshot?->profile_data['structured_profile'] ?? [];
        if ($snapshot) {
            $item['company_name'] = $snapshot->profile_data['company']['name'] ?? '会社名未確認';
        }
        $rows = [];
        foreach (['design_target' => ['設計対象', 'design-title'], 'design_phases' => ['主な工程', 'phases-title'], 'initial_assignment' => ['入社直後', 'assignment-title'], 'future_scope' => ['将来的な担当可能性', 'assignment-title'], 'tools' => ['CAD / Tool', 'tools-title'], 'customer_contact' => ['顧客', 'collaboration-title'], 'manufacturing_relation' => ['製造', 'collaboration-title'], 'site_relation' => ['現場', 'collaboration-title'], 'work_style' => ['仕事の進め方', 'work-style-title'], 'difficult_points' => ['仕事の難しさ', 'difficulties-title']] as $key => [$label, $anchor]) {
            $values = match ($key) {
                'design_phases' => array_map(fn ($phase) => StructuredJobOptions::PHASES[$phase] ?? '未確認', $profile[$key] ?? []),
                'tools' => array_map(fn ($tool) => ($tool['tool_name'] ?? '未確認').' / '.(StructuredJobOptions::USAGES[$tool['usage_context'] ?? ''] ?? '未確認').' / '.(StructuredJobOptions::EXPECTATIONS[$tool['experience_expectation'] ?? ''] ?? '未確認'), $snapshot?->profile_data['tool_usages'] ?? []),
                'customer_contact', 'manufacturing_relation', 'site_relation' => [StructuredJobOptions::FREQUENCIES[$profile[$key.'_frequency'] ?? ''] ?? null, $profile[$key.'_note'] ?? null],
                default => [$profile[$key] ?? null],
            };
            if ($snapshot === null && $key === 'tools') {
                // A legacy mention is not proof of use in the employee's responsibility.
                $values = $item['job']->getRelation('jobFacts')->where('fact_category', 'tool')
                    ->map(fn ($fact) => '記載：'.($fact->normalized_value ?: $fact->fact_value ?: $fact->fact_key).'（用途は詳細で確認）')->unique()->values()->all();
            }
            $rows[$key] = ['label' => $label, 'anchor' => $snapshot ? $anchor : 'presence-title', 'values' => array_values(array_map(fn ($value) => Str::limit($value, 80), array_filter($values, fn ($value) => is_string($value) && trim($value) !== '')))];
        }
        $item['comparison'] = ['rows' => $rows, 'snapshot' => (bool) $snapshot,
            'publisher' => $snapshot?->profile_data['provenance']['publisher'] ?? null,
            'published_at' => $snapshot?->published_at?->timezone('Asia/Tokyo')->format('Y年m月d日 H:i'),
            'routes' => collect(['direct' => '企業へ直接応募', 'agent' => '人材紹介会社', 'platform' => '求人媒体'])->filter(fn ($label, $type) => $routes->contains('route_type', $type))->all()];

        return $item;
    }
}
