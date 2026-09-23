<?php

namespace App\Support;

use App\Models\JobPublishedProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Display-only projection of the approved snapshot and published facts. Never loads authoring relations. */
class PublishedJobDecisionPresenter
{
    public const MISSING = 'この情報はまだ確認できていません';

    public function present(JobPublishedProfile $snapshot, array $fit, Collection $facts, Collection $routes): array
    {
        $data = $snapshot->profile_data;
        $basic = $data['level_one'] ?? [];
        $profile = $data['structured_profile'] ?? [];
        $provenance = $data['provenance'] ?? [];
        $source = ['url' => $provenance['url'] ?? null, 'provider_key' => $provenance['publisher'] ?? null,
            'published_at' => $snapshot->published_at?->toIso8601String()];
        $tools = collect($data['tool_usages'] ?? [])->sortBy('sort_order')->values()->map(function ($tool) use ($fit) {
            $axis = collect($fit['axes'])->firstWhere('key', 'tool_use:'.($tool['tool_key'] ?? ''));

            return ['name' => $this->text($tool['tool_name'] ?? null),
                'fields' => $this->toolFields($tool), 'status' => $axis['status'] ?? 'unknown',
                'fit_note' => $axis === null ? 'このツールは希望条件との照合対象に選ばれていません。'
                    : (($axis['status'] === 'unknown') ? '企業申告は表示材料です。現在の照合ルールでは使用を確認できていません。'
                        : (JobDecisionPresenter::REASONS[$axis['reason_code']] ?? self::MISSING))];
        })->all();
        $phases = $this->labels($profile['design_phases'] ?? [], StructuredJobOptions::PHASES);
        $points = array_filter([
            filled($profile['design_target'] ?? null) ? '設計対象：'.$profile['design_target'] : null,
            $phases !== [] ? '担当工程：'.implode('・', $phases) : null,
            filled($profile['initial_assignment'] ?? null) ? '入社直後：'.$profile['initial_assignment'] : null,
            isset($tools[0]) ? $tools[0]['name'].'：'.$tools[0]['fields']['仕事での使用'] : null,
            filled($profile['work_style'] ?? null) ? '仕事の進め方：'.$profile['work_style'] : null,
        ]);
        // Missing optional profile values never become negative claims or recommendations.
        foreach (['occupation' => '職種', 'region' => '勤務地', 'employment_type' => '雇用形態'] as $key => $label) {
            if (count($points) < 3 && filled($basic[$key] ?? null)) {
                $points[] = $label.'：'.$basic[$key];
            }
        }
        $sections = [
            ['id' => 'key-points', 'title' => 'この求人の要点', 'type' => 'points', 'points' => array_map(fn ($point) => Str::limit($point, 160), array_values($points))],
            ['id' => 'fit', 'title' => 'あなたの希望との照合', 'type' => 'fit'],
            $this->section('design', '何を設計する仕事か', $profile, ['design_target' => '設計対象', 'product_context' => '製品・設備・システムの用途や背景']),
            ['id' => 'phases', 'title' => 'どの工程を担当するか', 'fields' => ['担当工程' => $this->joined($phases)]],
            $this->section('assignment', '入社直後 → 将来', $profile, ['initial_assignment' => '入社直後の担当', 'future_scope' => '将来的な担当可能性（確約ではありません）']),
            ['id' => 'tools', 'title' => 'CAD / Tool', 'type' => 'tools', 'tools' => $tools],
            ['id' => 'collaboration', 'title' => '誰と仕事をするか', 'fields' => $this->collaboration($profile)],
            $this->section('work-style', '仕事の進め方', $profile, ['work_style' => '仕事の進め方', 'project_duration' => '案件の期間', 'concurrent_projects' => '同時に担当する案件']),
            ['id' => 'typical-day', 'title' => '代表的な1日', 'type' => 'day', 'items' => collect($data['typical_day_items'] ?? [])->sortBy('sort_order')->values()->map(fn ($item) => ['time' => $this->text($item['time_label'] ?? null), 'activity' => $this->text($item['activity'] ?? null)])->all()],
            $this->section('difficulties', 'この仕事の難しいところ', $profile, ['difficult_points' => '仕事の難しさ', 'onboarding_challenges' => '入社後につまずきやすい点']),
            $this->section('fit-style', '合いやすい働き方', $profile, ['fit_work_style' => '仕事のスタイル']),
            $this->section('misfit-style', '合いにくい可能性がある働き方', $profile, ['misfit_work_style' => '仕事のスタイル']),
            ['id' => 'project', 'title' => '代表的な案件', 'fields' => $this->projectFields($profile['representative_project'] ?? [])],
            $this->section('requirements', '応募条件と補足', [...$profile, 'application_requirements' => $basic['application_requirements'] ?? null], ['application_requirements' => '最低限の応募条件', 'required_experience' => '必要な経験', 'preferred_experience' => '歓迎する経験', 'hard_to_convey' => '求人票では伝わりにくいこと']),
            ['id' => 'evidence', 'title' => '情報源と根拠', 'type' => 'evidence'],
            ['id' => 'routes', 'title' => '応募方法', 'type' => 'routes', 'count' => $routes->count()],
        ];

        return ['company' => $this->text($data['company']['name'] ?? null), 'title' => $this->text($basic['title'] ?? null),
            'basic' => ['職種' => $this->text($basic['occupation'] ?? null), '勤務地' => $this->text($basic['region'] ?? null),
                '掲載年収' => (isset($basic['salary_min']) ? $basic['salary_min'].'万円' : '下限未確認').' 〜 '.(isset($basic['salary_max']) ? $basic['salary_max'].'万円' : '上限未確認'),
                '雇用形態' => $this->text($basic['employment_type'] ?? null)],
            'sections' => $sections, 'source' => $source, 'source_title' => $this->text($provenance['title'] ?? null),
            'published_at' => $snapshot->published_at?->timezone('Asia/Tokyo')->format('Y年m月d日 H:i（日本時間）') ?? self::MISSING,
            'evidence' => $facts->map(fn ($fact) => $this->fact($fact))->all()];
    }

    private function section(string $id, string $title, array $profile, array $labels): array
    {
        $fields = [];
        foreach ($labels as $key => $label) {
            $fields[$label] = $this->text($profile[$key] ?? null);
        }

        return compact('id', 'title', 'fields');
    }

    private function collaboration(array $profile): array
    {
        $fields = ['一緒に働く相手' => $this->joined($this->labels($profile['collaborators'] ?? [], StructuredJobOptions::COLLABORATORS))];
        foreach (['customer_contact' => '顧客', 'manufacturing_relation' => '製造', 'site_relation' => '現場'] as $key => $label) {
            $fields[$label.'との接点の頻度'] = StructuredJobOptions::FREQUENCIES[$profile[$key.'_frequency'] ?? ''] ?? self::MISSING;
            $fields[$label.'との関わり方'] = $this->text($profile[$key.'_note'] ?? null);
        }

        return $fields;
    }

    private function toolFields(array $tool): array
    {
        return ['仕事での使用' => StructuredJobOptions::USAGES[$tool['usage_context'] ?? ''] ?? self::MISSING,
            '応募時の経験要件' => StructuredJobOptions::EXPECTATIONS[$tool['experience_expectation'] ?? ''] ?? self::MISSING,
            '使用の補足' => $this->text($tool['usage_notes'] ?? null)];
    }

    private function projectFields(array $project): array
    {
        return ['何を作ったか' => $this->text($project['what_made'] ?? null),
            '担当した工程' => $this->joined($this->labels($project['phases'] ?? [], StructuredJobOptions::PHASES)),
            '期間' => $this->text($project['duration'] ?? null), 'チーム' => $this->text($project['team'] ?? null),
            '難しかった点' => $this->text($project['difficult_point'] ?? null)];
    }

    private function fact($fact): array
    {
        $selfReported = $fact->extraction_method === 'company_self_reported';
        $labels = array_merge(...array_values(StructuredJobOptions::FIELDS));
        $label = $selfReported ? ($labels[$fact->fact_key] ?? '企業が申告した情報') : $this->text($fact->normalized_value ?: $fact->fact_value);
        $text = $this->text($fact->evidence_text);
        $formatted = false;
        if ($selfReported && in_array($fact->fact_category, ['tool_usage', 'tool_expectation', 'project_example'])) {
            $stored = json_decode($fact->evidence_text ?? '', true);
            $formatted = true;
            $text = '保存された構造化根拠の詳細はまだ確認できていません。';
            if (is_array($stored)) {
                $fields = $fact->fact_category === 'project_example' ? $this->projectFields($stored)
                    : ['ツール名' => $this->text($stored['tool_name'] ?? null), ...$this->toolFields($stored)];
                $text = implode("\n", array_map(fn ($key, $value) => $key.'：'.$value, array_keys($fields), array_values($fields)));
            }
            $label = match ($fact->fact_category) {
                'tool_usage' => 'ツールの使用文脈', 'tool_expectation' => 'ツールの経験要件', default => '代表的な案件',
            };
        } elseif ($selfReported && $fact->fact_category === 'design_phase') {
            $label = '担当工程：'.(StructuredJobOptions::PHASES[$fact->fact_key] ?? self::MISSING);
        } elseif ($selfReported && $fact->fact_category === 'collaboration') {
            $label = '一緒に働く相手・関わり方';
        }
        $source = $fact->getRelation('source');

        return ['label' => $label, 'kind_label' => $selfReported ? '企業提供情報（申告内容）' : '掲載情報の根拠',
            'text_label' => $formatted ? '保存された根拠（日本語表示）' : '根拠の原文',
            'evidence' => ['kind' => 'job_fact', 'evidence_text' => $text,
                'context' => ['role' => $fact->context_role ?? 'unknown', 'reason' => $selfReported ? '企業が申告した内容です。公開確認は真偽の保証を意味しません。' : '保存された根拠です。'],
                'observed_at' => $fact->observed_at?->toIso8601String()],
            'source' => ['url' => $source?->url, 'provider_key' => $source?->publisher, 'last_seen_at' => $source?->fetched_at],
        ];
    }

    private function text(mixed $value): string
    {
        return is_string($value) && trim($value) !== '' ? $value : self::MISSING;
    }

    private function labels(array $keys, array $options): array
    {
        return array_values(array_map(fn ($key) => $options[$key] ?? self::MISSING, $keys));
    }

    private function joined(array $values): string
    {
        return $values === [] ? self::MISSING : implode('・', $values);
    }
}
