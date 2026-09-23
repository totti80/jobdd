<?php

namespace App\Support;

use App\Models\UserQuery;
use Illuminate\Validation\Rule;

/** Optional comparison material, deliberately separate from confirmed Fit requirements. */
class SeekerPreferences
{
    public const STORAGE_KEY = 'seeker_preferences';

    public const RELATIONS = ['customer_contact' => '顧客', 'manufacturing_relation' => '製造', 'site_relation' => '現場'];

    public const TENDENCIES = ['want_more' => '積極的に関わりたい', 'neutral' => 'どちらでもよい', 'want_less' => '関わりは少なめがよい', 'no_preference' => '特に希望なし'];

    public static function rules(): array
    {
        $rules = ['design_phases' => ['sometimes', 'array', 'list', 'max:9'],
            'design_phases.*' => ['required', 'string', 'distinct', Rule::in(array_keys(StructuredJobOptions::PHASES))],
            'work_style' => ['nullable', 'string', 'max:2000']];
        foreach (self::RELATIONS as $key => $label) {
            $rules[$key] = ['nullable', 'string', Rule::in(array_keys(self::TENDENCIES))];
        }

        return $rules;
    }

    public static function read(UserQuery $query): array
    {
        $stored = $query->detailed_skills;

        return self::values(is_array($stored) && is_array($stored[self::STORAGE_KEY] ?? null) ? $stored[self::STORAGE_KEY] : []);
    }

    public static function values(array $input): array
    {
        $phases = is_array($input['design_phases'] ?? null) ? array_filter($input['design_phases'], 'is_string') : [];
        $values = ['design_phases' => array_values(array_intersect(array_keys(StructuredJobOptions::PHASES), $phases))];
        foreach (self::RELATIONS as $key => $label) {
            $value = $input[$key] ?? null;
            $values[$key] = is_string($value) && isset(self::TENDENCIES[$value]) ? $value : null;
        }
        $values['work_style'] = is_string($input['work_style'] ?? null) && trim($input['work_style']) !== '' ? trim($input['work_style']) : null;

        return $values;
    }

    /** Preserve all legacy intent for Fit, excluding only this display-only namespace. */
    public static function fitInput(UserQuery $query): UserQuery
    {
        $skills = $query->detailed_skills;
        if (! is_array($skills) || ! array_key_exists(self::STORAGE_KEY, $skills)) {
            return $query;
        }
        unset($skills[self::STORAGE_KEY]);
        $input = clone $query;
        $input->detailed_skills = $skills ?: null;

        return $input;
    }

    public static function comparison(array $preferences, array $profile): array
    {
        $rows = [];
        $missing = '求人側の情報はまだ確認できていません';
        if (! empty($preferences['design_phases'])) {
            $rows[] = ['label' => '担当工程', 'preference' => implode('・', array_map(fn ($key) => StructuredJobOptions::PHASES[$key], $preferences['design_phases'])),
                'job' => empty($profile['design_phases']) ? $missing : implode('・', array_map(fn ($key) => StructuredJobOptions::PHASES[$key] ?? $missing, $profile['design_phases']))];
        }
        foreach (self::RELATIONS as $key => $label) {
            if (filled($preferences[$key] ?? null)) {
                $job = array_filter([StructuredJobOptions::FREQUENCIES[$profile[$key.'_frequency'] ?? ''] ?? null, $profile[$key.'_note'] ?? null], fn ($value) => filled($value));
                $rows[] = ['label' => $label.'との関わり', 'preference' => self::TENDENCIES[$preferences[$key]], 'job' => $job === [] ? $missing : implode("\n", $job)];
            }
        }
        if (filled($preferences['work_style'] ?? null)) {
            $rows[] = ['label' => '仕事の進め方', 'preference' => $preferences['work_style'], 'job' => filled($profile['work_style'] ?? null) ? $profile['work_style'] : $missing];
        }

        return $rows;
    }
}
