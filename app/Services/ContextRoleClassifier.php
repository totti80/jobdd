<?php

namespace App\Services;

use App\Models\JobFact;
use App\Models\JobPosting;

/** Derives context from saved text only. Never queries or changes a model. */
class ContextRoleClassifier
{
    public const ROLES = [
        'responsibility', 'required_experience', 'preferred_experience',
        'collaboration', 'other_department', 'company_context',
        'product_context', 'project_example', 'unknown',
    ];

    /**
     * Offsets refer to UTF-8 characters in normalized_text, not source HTML.
     * A role describes a textual assertion, never a guarantee of assignment.
     */
    public function classify(JobPosting $job, JobFact $fact): array
    {
        $text = $this->text((string) $job->description);
        $evidence = $this->text((string) $fact->evidence_text);
        $base = ['role' => 'unknown', 'reason' => '', 'matched_contexts' => [], 'notes' => [], 'normalized_text' => $text];

        if ($text === '' || $evidence === '' || ($job->id !== null && $fact->job_posting_id !== null && $job->id != $fact->job_posting_id)) {
            return [...$base, 'reason' => 'Raw/Evidenceがない、または求人とFactの対応が不一致'];
        }

        $definition = null;
        foreach (JobFactDictionary::definitions() as $candidate) {
            if ($candidate['fact_key'] === $fact->fact_key && $candidate['fact_category'] === $fact->fact_category) {
                $definition = $candidate;
                break;
            }
        }
        if ($definition === null) {
            return [...$base, 'reason' => '既存辞書でFactの同一性を確認できない'];
        }

        $aliases = $definition['aliases'];
        usort($aliases, fn ($a, $b) => strlen($b) <=> strlen($a));
        $pattern = '/(?:'.implode('|', array_map(function ($alias) {
            $quoted = preg_quote($alias, '/');

            return preg_match('/^[A-Za-z0-9][A-Za-z0-9 .-]*[A-Za-z0-9]$/', $alias)
                ? '(?<![A-Za-z0-9])'.$quoted.'(?![A-Za-z0-9])' : $quoted;
        }, $aliases)).')/iu';
        if (! preg_match($pattern, $evidence)) {
            return [...$base, 'reason' => '保存Evidence内に対象Factを確認できない'];
        }
        preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);
        if ($matches[0] === []) {
            return [...$base, 'reason' => '現在のRawに対象Factの出現がない'];
        }

        $sections = $this->sections($text);
        $contexts = [];
        foreach ($matches[0] as [$value, $byteOffset]) {
            $offset = mb_strlen(substr($text, 0, $byteOffset));
            $section = $sections[0];
            foreach ($sections as $part) {
                if ($part['start'] > $offset) {
                    break;
                }
                $section = $part;
            }
            $before = mb_substr($text, $section['start'], $offset - $section['start']);
            $after = mb_substr($text, $offset, $section['end'] - $offset);
            $prefix = preg_split('/[。！？\n]/u', $before);
            $suffix = preg_split('/[。！？\n]/u', $after);
            $sentence = end($prefix).$suffix[0];
            $localOffset = mb_strlen((string) end($prefix));
            $decision = $this->occurrence($sentence, $localOffset, $value, $section, $before);
            $contexts[] = [
                'match' => $value, 'offset' => $offset, 'length' => mb_strlen($value),
                'section' => $section['heading'], 'section_role' => $section['role'],
                'text' => $sentence, 'role' => $decision[0], 'reason' => $decision[1],
            ];
        }

        [$role, $reason, $roles] = $this->aggregate($contexts);

        return [...$base, 'role' => $role, 'reason' => $reason, 'matched_contexts' => $contexts,
            'notes' => count($roles) > 1 ? ['複数文脈: '.implode(', ', $roles)] : []];
    }

    private function aggregate(array $contexts): array
    {
        $roles = array_values(array_unique(array_column($contexts, 'role')));
        // An explicit duty and an experience requirement can both be true.
        // Unknown/other contextual occurrences remain a conflict, not positive evidence.
        if (count($roles) === 1) {
            $role = $roles[0];
            $reason = $contexts[0]['reason'];
        } elseif (in_array('responsibility', $roles, true)
            && array_diff($roles, ['responsibility', 'required_experience', 'preferred_experience']) === []) {
            $role = 'responsibility';
            $reason = '本人担当の明示と応募経験の両方を確認。担当の根拠と条件を別々に保持';
        } elseif (array_diff($roles, ['required_experience', 'preferred_experience']) === []) {
            $role = 'required_experience';
            $reason = '必須・歓迎の両文脈が存在。必須の記載をprimaryとし、全出現を保持';
        } else {
            $role = 'unknown';
            $reason = '同一Factの複数出現で文脈が競合するため、本人担当を断定しない';
        }

        return [$role, $reason, $roles];
    }

    private function text(string $html): string
    {
        // Japanese pseudo-tags are textual headings, not HTML elements.
        $html = preg_replace('/<([\p{Han}\p{Hiragana}\p{Katakana}][^<>\n]{0,59})>/u', '【$1】', $html);
        $html = preg_replace('/<h[1-6]\b[^>]*>(.*?)<\/h[1-6]>/isu', '【$1】', $html);
        $html = preg_replace('/<(?:br\b[^>]*|\/(?:p|div|li|h[1-6]))>/iu', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/[^\S\n]+/u', ' ', str_replace(["\r\n", "\r"], "\n", $text)));
    }

    private function sections(string $text): array
    {
        // Bracket headings always end the previous section, including unknown ones.
        $labels = '仕事内容|仕事の内容|業務内容|職務内容|担当業務|具体的には|応募条件|必要な経験|求める経験|必須条件|歓迎条件|必須|歓迎|MUST|WANT|尚可|優遇|企業概要|会社概要|事業内容|製品群例|製品群|製品|案件例|開発例|プロジェクト例|配属例|使用ツール';
        $pattern = '/【[^】\n]{1,60}】|\[[^\]\n]{1,60}\]|＜[^＞\n]{1,60}＞|《[^》\n]{1,60}》|「[^」\n]{1,60}」|'
            .'(?:仕事内容|仕事の内容|業務内容|職務内容|担当業務|具体的には|必要な経験・能力等|募集職種|求めている人材|学歴・資格|職場環境|配属先情報)'
            .'|[■◆●▼](?:'.$labels.')[：: ]*'
            .'|(?:^|\n)[ \t]*(?:'.$labels.')(?=[：: \n]|$)[：: ]*/mu';
        preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);
        $parts = [['start' => 0, 'heading' => '', 'role' => 'unknown']];
        foreach ($matches[0] as [$heading, $position]) {
            $start = mb_strlen(substr($text, 0, $position));
            $role = $this->headingRole($heading);
            // A nominal preview immediately before an inline examples heading
            // belongs to those examples, not an earlier duty introduction.
            if ($role === 'project_example') {
                $prefix = preg_split('/[。！？\n]/u', mb_substr($text, 0, $start));
                $preview = (string) end($prefix);
                if ($preview !== '' && ! preg_match('/担当|お任せ|行います|使用|携わ|従事|[】＞》」\]]/u', $preview)) {
                    $start = max($parts[array_key_last($parts)]['start'], $start - mb_strlen($preview));
                }
            }
            $parts[] = ['start' => $start, 'heading' => $heading, 'role' => $role];
        }
        foreach ($parts as $i => &$part) {
            $part['end'] = $parts[$i + 1]['start'] ?? mb_strlen($text);
        }

        return $parts;
    }

    private function headingRole(string $heading): string
    {
        return match (true) {
            (bool) preg_match('/歓迎|WANT|尚可|優遇/u', $heading) => 'preferred_experience',
            (bool) preg_match('/必須|MUST|応募条件|必要条件|必要な経験|求める経験/u', $heading) => 'required_experience',
            (bool) preg_match('/案件例|開発例|プロジェクト例|配属例|製品群例|携われる業界|打診ポジション例/u', $heading) => 'project_example',
            (bool) preg_match('/企業概要|会社概要|当社について|事業内容|事業概要|スキルアップ|キャリア/u', $heading) => 'company_context',
            (bool) preg_match('/製品|取扱/u', $heading) => 'product_context',
            (bool) preg_match('/仕事内容|仕事の内容|業務内容|職務内容|担当業務|具体的には|業務詳細|担当工程|使用ツール|ツール.*環境/u', $heading) => 'responsibility',
            default => 'unknown',
        };
    }

    private function occurrence(string $sentence, int $offset, string $value, array $section, string $before): array
    {
        $left = mb_substr($sentence, 0, $offset);
        $right = mb_substr($sentence, $offset + mb_strlen($value));
        $role = $section['role'];
        $near = mb_substr($left, -70).$value.mb_substr($right, 0, 100);

        if (preg_match('/\.{3}|…/u', $sentence)) {
            return ['unknown', '対象文に省略があり、主体・係り受けを安全に確認できない'];
        }
        if (preg_match('/(?:担当|使用|実施|行[わい])(?:し|する)?(?:ない|ません)|対象外|不要|担当しません|使用しません/u', $near)) {
            return ['unknown', '否定・対象外の表現があり、肯定的な担当Factにしない'];
        }
        if (preg_match('/^(?:・[^、。]{0,12})?(?:部門|部署|チーム|担当者)/u', $right)
            && preg_match('/連携|調整|共に|協力|インターフェース/u', $sentence)) {
            return ['collaboration', 'Fact語が連携・調整先の部門名を修飾している'];
        }
        if (preg_match('/(?:他部署|他部門|別職種|別部門)[^。]{0,35}(?:が|は)/u', $left)
            || preg_match('/(?:は|を)[^。]{0,12}(?:他部署|他部門|別職種|別部門)[^。]{0,12}(?:担当|実施)/u', $right)) {
            return ['other_department', '別部門・別職種がFactの実施主体として明示されている'];
        }
        if (preg_match('/当社使用|当社の使用|全社|会社全体/u', $sentence)) {
            return ['company_context', '会社全体の使用環境・技術説明で、本人の使用を個別に確認できない'];
        }
        // Inline requirements bind to the assertion, not every nearby responsibility.
        if (preg_match('/(?:経験|スキル|知識|利用)[^。]{0,15}(?:歓迎|尚可|優遇)/u', $right)
            || preg_match('/(?:歓迎|尚可|優遇)[：:]\s*[^。：:]{0,25}$/u', $left)) {
            return ['preferred_experience', '対象Factの経験・スキルが歓迎条件として明示'];
        }
        if (preg_match('/(?:経験|スキル|知識)[^。]{0,15}(?:必須|必要)/u', $right)) {
            return ['required_experience', '対象Factの経験・スキルが必須条件として明示'];
        }
        if (in_array($role, ['required_experience', 'preferred_experience'], true)) {
            return [$role, $role === 'required_experience' ? '応募必須・必要経験の節での記載' : '歓迎・尚可条件の節での記載'];
        }
        if ($role === 'company_context' && preg_match('/企業概要|会社概要|事業内容|事業概要/u', $section['heading'])
            && preg_match('/製品|装置|システム|サービス/u', $sentence)) {
            return ['product_context', '企業紹介の節で製品・システムそのものを説明'];
        }
        if (in_array($role, ['company_context', 'product_context', 'project_example'], true)) {
            return [$role, '会社・製品・案件の説明節であり、個別の担当確定とは区別'];
        }
        if (preg_match('/案件例|開発例|プロジェクト例|配属例|候補業務|過去の案件/u', $sentence)) {
            return ['project_example', '対象文が案件・配属候補を示している'];
        }

        $action = '/担当(?:して|します|する|いただ|頂)|お任せ|行(?:います|って|う)|使用(?:して|します|する)|用いて|携わ(?:って|り|る)|従事|進めて/u';
        $hasAction = (bool) preg_match($action, $sentence);
        $intro = mb_substr($before, 0, 500);
        $isDutyList = $role === 'responsibility' && preg_match($action, $intro);
        $isToolSection = $role === 'responsibility' && preg_match('/使用ツール|ツール.*環境/u', $section['heading']);
        if (($hasAction && in_array($role, ['responsibility', 'unknown'], true)) || $isDutyList || $isToolSection) {
            if (preg_match('/経験|スキル|できる方|可能な方|当社では|社員|エンジニアとして|可能です/u', $sentence)) {
                return ['unknown', '経験条件・会社の一般論との区別が不十分なため担当を断定しない'];
            }

            return ['responsibility', $hasAction ? '対象文で担当・実施・使用が明示されている' : '本人担当を明示した業務節の項目、または業務の使用ツール欄'];
        }

        return ['unknown', '節と対象文から本人の役割を安全に特定できない'];
    }
}
