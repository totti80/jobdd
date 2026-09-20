<?php

namespace App\Services;

use App\Models\JobPosting;
use App\Support\AnonymousCompany;
use App\Support\JobDecisionPresenter;
use Illuminate\Support\Facades\DB;

class DiscoveryCoverageService
{
    public function snapshot(): array
    {
        $jobs = JobPosting::query()->whereNull('unavailable_at')
            ->whereIn('occupation', DailyDiscoveryService::OCCUPATIONS)->whereIn('region', DailyDiscoveryService::REGIONS);
        $ids = fn () => (clone $jobs)->select('id');
        $facts = DB::table('job_facts')->whereIn('job_posting_id', $ids());
        $routes = DB::table('application_routes')->whereIn('job_posting_id', $ids());
        $withFacts = (clone $facts)->distinct()->count('job_posting_id');
        $total = (clone $jobs)->count();
        $depth = [
            'jobs_with_any_fact' => $withFacts, 'jobs_without_fact' => $total - $withFacts,
            'total_job_facts' => (clone $facts)->count(), 'tier1_like_facts' => null,
            'tier2_like_facts' => (clone $facts)->whereIn('fact_key', array_column(JobFactDictionary::definitions(), 'fact_key'))->count(),
            'jobs_with_source_url' => (clone $jobs)->whereNotNull('source_url')->where('source_url', '!=', '')->count(),
        ];
        foreach (['direct', 'agent', 'platform'] as $type) {
            $depth['jobs_with_'.$type.'_route'] = (clone $routes)->where('route_type', $type)
                ->where('availability_status', 'available')->whereNull('unavailable_at')->distinct()->count('job_posting_id');
        }
        // Presence metrics only, aligned with Batch 14's display keys, not consultation quality.
        $agent = ['agencies_total' => DB::table('agencies')->count(), 'agency_facts_total' => DB::table('agency_facts')->count()];
        foreach (['domain_fit' => ['supported_occupation', 'supported_region'], 'opportunity_access' => ['non_public_jobs'],
            'advisory_fit' => ['technical_advisor', 'career_consultation', 'document_support', 'interview_support'],
            'outcome_evidence' => ['manufacturing_placement']] as $layer => $keys) {
            $agent['agencies_with_'.$layer.'_data'] = DB::table('agency_facts')->whereIn('fact_key', $keys)->distinct()->count('agency_id');
        }

        $companiesTotal = (clone $jobs)->distinct()->count('company_id');
        $anonymousCompanies = DB::table('companies')->whereIn('id', (clone $jobs)->select('company_id'))
            ->where('name', AnonymousCompany::NAME)->count();

        return [
            'definition' => 'Active stored postings in the 12 exact canonical cells; not verified market coverage. Company counts include storage placeholders, not unique verified employers; named/anonymous counts separate them. Source URL means stored nonempty value. Routes require saved available state. Agency metrics count key presence, including unverified/test data.',
            'current_db_coverage' => ['active_jobs' => $total, 'companies' => $companiesTotal,
                'companies_total' => $companiesTotal, 'anonymous_companies' => $anonymousCompanies,
                'named_companies' => $companiesTotal - $anonymousCompanies,
                'sources' => DB::table('sources')->whereIn('url', (clone $jobs)->select('source_url'))->count(),
                'application_routes' => (clone $routes)->count(), 'job_facts' => (clone $facts)->count()],
            'evidence_depth' => $depth, 'agent_fact_metrics' => $agent,
            'by_cell' => (clone $jobs)->selectRaw('occupation, region, provider_key, COUNT(*) AS jobs')
                ->groupBy('occupation', 'region', 'provider_key')->orderBy('occupation')->orderBy('region')->orderBy('provider_key')->get()->toArray(),
        ];
    }

    public function directCandidates(array $changes): array
    {
        $changes = collect($changes)->whereIn('state', ['new', 'updated'])
            ->reject(fn ($row) => AnonymousCompany::isAnonymous($row['company_name'])
                || in_array(trim($row['company_name'] ?? ''), ['', '不明', '非公開', '企業名非公開', '企業名未確認', '会社名未確認'], true));
        $ids = $changes->pluck('company_id')->filter()->unique()->values()->all();
        $companies = DB::table('companies')->whereIn('id', $ids)->pluck('website_url', 'id');
        $direct = DB::table('application_routes')->join('job_postings', 'job_postings.id', '=', 'application_routes.job_posting_id')
            ->whereIn('job_postings.company_id', $ids)->where('route_type', 'direct')
            ->where('availability_status', 'available')->whereNull('application_routes.unavailable_at')
            ->distinct()->pluck('company_id')->all();

        return $changes->groupBy(fn ($row) => $row['company_id'] === null ? 'new:'.$row['company_name'] : 'id:'.$row['company_id'])
            ->map(fn ($rows) => [
                'company_id' => $rows[0]['company_id'], 'company_name' => $rows[0]['company_name'],
                'job_ids' => $rows->pluck('job_id')->filter()->unique()->values()->all(),
                'existing_direct_route' => in_array($rows[0]['company_id'], $direct, true),
                'official_source_known' => JobDecisionPresenter::safeUrl($companies[$rows[0]['company_id']] ?? null) !== null,
            ])->values()->all();
    }
}
