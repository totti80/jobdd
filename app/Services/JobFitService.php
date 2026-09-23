<?php

namespace App\Services;

use App\Models\JobFact;
use App\Models\JobPosting;
use App\Models\UserQuery;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use Throwable;

/** Compares loaded snapshots only: no queries, persistence, scores or ranking. */
class JobFitService
{
    public const FIT_VERSION = 'jobfit-v0.1';

    public const CLASSIFIER_VERSION = 'batch43:a50aba5bcfe09966f6f8bbe141b12d67affc6e210e6adeea1ebc449445e7df9d';

    private const TOOLS = ['autocad', 'inventor', 'solidworks', 'catia', 'creo', 'nx', 'electrical_cad'];

    private const OCCUPATIONS = ['機械設計', '電気設計'];

    private const REGIONS = ['兵庫県', '大阪府', '京都府', '滋賀県', '奈良県', '和歌山県'];

    public function __construct(
        private ContextRoleClassifier $classifier,
        private OccupationNormalizer $occupationNormalizer,
    ) {}

    /**
     * @param  list<JobFact>  $facts  All saved Facts for this job, loaded by the caller.
     * @param  mixed  $confirmedRequirements  An array; mixed permits a safe contract exception for invalid types.
     */
    public function evaluate(UserQuery $query, JobPosting $job, array $facts, mixed $confirmedRequirements = []): array
    {
        $definitions = array_column(JobFactDictionary::definitions(), null, 'fact_key');
        [$desired, $hard] = $this->validateInputs($job, $facts, $confirmedRequirements, $definitions);
        // Company declarations are displayed with explicit roles; no new Fit axes or classifier inference.
        $facts = array_values(array_filter($facts, fn ($fact) => $fact->extraction_method !== 'company_self_reported'));
        usort($facts, fn (JobFact $a, JobFact $b) => (int) $a->id <=> (int) $b->id);
        $axes = [];
        $notes = [];
        foreach (['occupation', 'region', 'salary'] as $key) {
            $value = $query->getAttribute($key === 'salary' ? 'salary_min' : $key);
            if ($value !== null) {
                $axes[] = $this->tierOne($key, $value, $query, $job);
            }
        }
        $classified = [];
        foreach ($desired as $request) {
            $selected = array_values(array_filter($facts, fn ($f) => $f->fact_key === $request['fact_key']));
            $axes[] = $this->tierTwo($request, $job, $selected, $classified, $definitions[$request['fact_key']]);
        }
        $keys = array_column($axes, 'key');
        if (array_diff($hard, $keys) !== []) {
            throw new InvalidArgumentException('hard_axes must name generated axes.');
        }
        $summary = ['evaluated_axes' => count($axes), 'confirmed_matches' => 0, 'confirmed_mismatches' => 0, 'unknowns' => 0, 'hard_mismatch_keys' => []];
        foreach ($axes as &$axis) {
            $axis['requirement']['hard'] = in_array($axis['key'], $hard, true);
            // Source provenance is required for every confirmed comparison.
            if ($axis['status'] !== 'unknown' && ! $this->validUrl($job->source_url)) {
                $axis['status'] = 'unknown';
                $axis['reason_code'] = 'evidence_missing';
            }
            $counter = ['match' => 'confirmed_matches', 'mismatch' => 'confirmed_mismatches', 'unknown' => 'unknowns'][$axis['status']];
            $summary[$counter]++;
            if ($axis['status'] === 'mismatch' && $axis['requirement']['hard']) {
                $summary['hard_mismatch_keys'][] = $axis['key'];
            }
        }
        unset($axis);
        if (! empty($query->detailed_skills)) {
            $notes[] = 'user_intent_unconfirmed';
        }
        $priorities = $query->priorities;
        if ($priorities !== null && $priorities !== []) {
            if (! is_array($priorities) || ! array_is_list($priorities) || count(array_filter($priorities, 'is_string')) !== count($priorities)) {
                $notes[] = 'unsupported_priorities_format';
            } else {
                if (array_diff($priorities, $keys) !== []) {
                    $notes[] = 'unsupported_priorities_format';
                }
                $first = array_slice(array_values(array_unique(array_intersect($priorities, $keys))), 0, 3);
                $order = array_flip(array_values(array_unique([...$first, ...$keys])));
                usort($axes, fn ($a, $b) => $order[$a['key']] <=> $order[$b['key']]);
            }
        }
        $source = ['url' => $job->source_url, 'provider_key' => $job->provider_key];
        foreach (['last_seen_at', 'published_at', 'provider_updated_at', 'unavailable_at'] as $field) {
            $source[$field] = $this->date($job->getAttributes()[$field] ?? null);
        }

        return ['job_posting_id' => $job->id, 'fit_version' => self::FIT_VERSION,
            'classifier_version' => self::CLASSIFIER_VERSION, 'source' => $source,
            'summary' => $summary, 'axes' => $axes, 'input_notes' => $notes];
    }

    private function validateInputs(JobPosting $job, array $facts, mixed $requirements, array $definitions): array
    {
        if (! is_array($requirements) || array_diff(array_keys($requirements), ['desired', 'hard_axes']) !== []) {
            throw new InvalidArgumentException('Invalid confirmedRequirements shape.');
        }
        $desired = $requirements['desired'] ?? [];
        $hard = $requirements['hard_axes'] ?? [];
        if ((array_key_exists('desired', $requirements) && $requirements['desired'] === null)
            || (array_key_exists('hard_axes', $requirements) && $requirements['hard_axes'] === null)
            || ! is_array($desired) || ! array_is_list($desired) || ! is_array($hard) || ! array_is_list($hard)
            || count(array_filter($hard, 'is_string')) !== count($hard) || ! array_is_list($facts)) {
            throw new InvalidArgumentException('Expected lists of desired, hard_axes and Facts.');
        }
        $unique = [];
        foreach ($desired as $item) {
            if (! is_array($item) || count($item) !== 2 || ! is_string($item['fact_key'] ?? null)
                || ! isset($definitions[$item['fact_key']]) || ! in_array($item['action'] ?? null, ['use', 'design', 'analyze'], true)) {
                throw new InvalidArgumentException('Invalid desired fact_key or action.');
            }
            $unique[$item['action'].':'.$item['fact_key']] = $item;
        }
        $ids = [];
        foreach ($facts as $fact) {
            if (! $fact instanceof JobFact || ! $this->positiveInteger($fact->id)
                || ! $this->positiveInteger($job->id) || ! $this->positiveInteger($fact->job_posting_id)
                || (int) $fact->job_posting_id !== (int) $job->id
                || ! is_string($fact->fact_key)
                || ($fact->extraction_method !== 'company_self_reported' && (! isset($definitions[$fact->fact_key])
                    || $definitions[$fact->fact_key]['fact_category'] !== $fact->fact_category))
                || isset($ids[(int) $fact->id])) {
                throw new InvalidArgumentException('Invalid, duplicate or foreign JobFact model.');
            }
            $ids[(int) $fact->id] = true;
        }

        return [array_values($unique), array_values(array_unique($hard))];
    }

    private function axis(string $key, mixed $value, ?string $action, mixed $jobValue, array $evidence): array
    {
        return ['key' => $key, 'requirement' => ['value' => $value, 'action' => $action, 'hard' => false],
            'status' => 'unknown', 'job_value' => $jobValue, 'reason_code' => 'missing_job_value', 'evidence' => $evidence, 'notes' => []];
    }

    private function tierOne(string $key, mixed $value, UserQuery $query, JobPosting $job): array
    {
        $fields = $key === 'salary' ? ['salary_min', 'salary_max'] : [$key];
        $evidence = array_map(fn ($field) => ['kind' => 'job_field', 'field' => $field, 'value' => $job->getAttribute($field)], $fields);
        $jobValue = $key === 'salary' ? ['min' => $job->salary_min, 'max' => $job->salary_max] : $job->getAttribute($key);
        $axis = $this->axis($key, $value, null, $jobValue, $evidence);
        if ($key === 'salary') {
            $axis['notes'][] = 'published_annualized_value_not_offer_guarantee';
            if ($query->salary_max !== null) {
                $decision = ['unknown', 'unsupported_salary_request'];
            } elseif (! $this->positiveInteger($value)) {
                $decision = ['unknown', 'unsupported_user_value'];
            } else {
                $min = $job->salary_min;
                $max = $job->salary_max;
                $decision = match (true) {
                    $min === null && $max === null => ['unknown', 'missing_job_value'],
                    ($min !== null && ! $this->positiveInteger($min)) || ($max !== null && ! $this->positiveInteger($max)) => ['unknown', 'invalid_job_value'],
                    $min !== null && $max !== null && $min > $max => ['unknown', 'invalid_job_value'],
                    $min !== null && $min >= $value => ['match', 'salary_floor_meets'],
                    $max !== null && $max < $value => ['mismatch', 'salary_ceiling_below'],
                    $min !== null && $max !== null => ['unknown', 'salary_overlap'],
                    default => ['unknown', 'missing_job_value'],
                };
            }
        } elseif (! in_array($value, $key === 'occupation' ? self::OCCUPATIONS : self::REGIONS, true)) {
            $decision = ['unknown', 'unsupported_user_value'];
        } elseif ($jobValue === null || $jobValue === '') {
            $decision = ['unknown', 'missing_job_value'];
        } elseif ($key === 'occupation') {
            $title = preg_replace('/[\s（）()]/u', '', (string) $job->title);
            $normalized = $this->occupationNormalizer->normalize($title, $job->description);
            if ($normalized !== null && $normalized !== $jobValue) {
                $decision = ['unknown', 'conflicting_job_evidence'];
                $axis['evidence'][] = ['kind' => 'job_field', 'field' => 'title', 'value' => $job->title];
                $axis['evidence'][] = ['kind' => 'job_field', 'field' => 'description', 'value' => $job->description];
            } elseif (! in_array($jobValue, self::OCCUPATIONS, true)) {
                $decision = ['unknown', 'invalid_job_value'];
            } else {
                $decision = $value === $jobValue ? ['match', 'same_occupation'] : ['mismatch', 'different_occupation'];
            }
        } else {
            // Count all prefecture-shaped mentions, including locations outside Kinki.
            preg_match_all('/(?:東京都|北海道|大阪府|京都府|[^\s、,\/・;:：都道府県]{2,3}県)/u', (string) $jobValue, $matches);
            $regions = array_values(array_unique($matches[0]));
            if (count($regions) !== 1 || ! in_array($regions[0], self::REGIONS, true)
                || ! str_starts_with(trim((string) $jobValue), $regions[0])
                || preg_match('/全国|未定|応相談|いずれか|または|又は/u', (string) $jobValue)) {
                $decision = ['unknown', 'invalid_job_value'];
            } else {
                $decision = $value === $regions[0] ? ['match', 'same_prefecture'] : ['mismatch', 'different_prefecture'];
            }
        }
        [$axis['status'], $axis['reason_code']] = $decision;

        return $axis;
    }

    private function tierTwo(array $request, JobPosting $job, array $facts, array &$classified, array $definition): array
    {
        $key = $request['fact_key'];
        $action = $request['action'];
        $axis = $this->axis(($action === 'use' ? 'tool_use' : $action).':'.$key, $key, $action, $facts[0]->normalized_value ?? null, []);
        $roles = $failures = [];
        $use = false;
        foreach ($facts as $fact) {
            $result = $classified[$fact->id] ??= $this->classifier->classify($job, $fact);
            $roles[] = $result['role'];
            $observed = $this->date($fact->getAttributes()['observed_at'] ?? null);
            $failure = match (true) {
                $fact->extraction_method !== 'rule' || $fact->verification_status !== 'verified' => 'fact_unverified',
                trim((string) $fact->normalized_value) === '' || trim((string) $fact->evidence_text) === ''
                    || trim((string) $job->description) === '' || $observed === null => 'evidence_missing',
                default => null,
            };
            if ($failure !== null) {
                $failures[] = $failure;
            }
            $axis['evidence'][] = ['kind' => 'job_fact', 'job_fact_id' => $fact->id, 'source_id' => $fact->source_id,
                'fact_key' => $fact->fact_key, 'normalized_value' => $fact->normalized_value,
                'evidence_text' => $fact->evidence_text, 'observed_at' => $observed,
                'context' => array_intersect_key($result, array_flip(['role', 'reason', 'matched_contexts', 'notes']))];
            if ($failure === null && $result['role'] === 'responsibility' && $this->confirmedUse($result, $definition['aliases'])) {
                $use = true;
            }
        }
        $supported = $action === 'use' && in_array($key, self::TOOLS, true);
        $conflict = count($facts) > 1 && ($failures !== [] || array_diff($roles, ['responsibility', 'required_experience', 'preferred_experience']) !== []);
        // Unsupported requests remain visible, with all available context as evidence.
        $axis['reason_code'] = match (true) {
            ! $supported => 'unsupported_tier2_requirement',
            $facts === [] => 'presence_not_found',
            $conflict => 'conflicting_contexts',
            $failures !== [] => $failures[0],
            ! $this->validUrl($job->source_url) => 'evidence_missing',
            in_array('unknown', $roles, true) => 'context_unknown',
            ! in_array('responsibility', $roles, true) => 'context_not_responsibility',
            ! $use => 'action_unconfirmed',
            default => 'confirmed_tool_use',
        };
        if ($facts === []) {
            $axis['notes'][] = 'absence_does_not_prove_non_use';
        }
        if ($axis['reason_code'] === 'confirmed_tool_use') {
            $axis['status'] = 'match';
        }

        return $axis;
    }

    private function confirmedUse(array $result, array $aliases): bool
    {
        $found = false;
        foreach ($result['matched_contexts'] as $context) {
            if ($context['role'] !== 'responsibility') {
                continue;
            }
            $text = $context['text'];
            if (! in_array(mb_strtolower($context['match']), array_map('mb_strtolower', $aliases), true)
                || mb_substr($result['normalized_text'], $context['offset'], $context['length']) !== $context['match']) {
                return false;
            }
            // Conservatively reject ambiguity anywhere in this assertion, not in unrelated experience sections.
            if (preg_match('/経験|歓迎|必須|不要|しない|しません|できない|せず|しなく|未使用|購入|発注|移行元|使用例|いずれか|又は|または|あるいは|\bOR\b|他部署|他部門|別部門|予定|可能性/iu', $text)) {
                return false;
            }
            $alias = preg_quote($context['match'], '/');
            $version = '(?:\s+(?:V\s*)?\d+(?:\.\d+)*)?';
            if (preg_match('/'.$alias.$version.'\s*(?:を使用|を利用|を用い|で設計)/iu', $text)) {
                $found = true;
            } elseif (preg_match('/使用ツール|ツール\s*[\/／]\s*開発環境/u', $context['section'])) {
                // Bare list items only; suffixes such as Electrical/LT must not expand an alias.
                $items = preg_split('/[、,，;；\n]/u', $text);
                foreach ($items as $item) {
                    $item = trim(str_replace($context['section'], '', $item), " \t：:");
                    if (preg_match('/^'.$alias.$version.'$/iu', $item)) {
                        $found = true;
                    }
                }
            }
        }

        return $found;
    }

    private function validUrl(mixed $url): bool
    {
        return is_string($url) && filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    private function positiveInteger(mixed $value): bool
    {
        return (is_int($value) || (is_string($value) && preg_match('/^[0-9]+$/D', $value))) && $value > 0;
    }

    private function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }
        // Do not let relative values such as "tomorrow" make evaluation time-dependent.
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})?)?$/D', $value)) {
            return null;
        }
        try {
            $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
            $errors = DateTimeImmutable::getLastErrors();
            if (is_array($errors) && ($errors['warning_count'] || $errors['error_count'])) {
                return null;
            }

            return $date->format(DateTimeInterface::ATOM);
        } catch (Throwable) {
            return null;
        }
    }
}
