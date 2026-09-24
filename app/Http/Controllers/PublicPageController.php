<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use App\Models\UserQuery;
use App\Services\JobDecisionUseCaseService;
use App\Services\JobSelectionUseCaseService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** Public entry points reuse existing query authorization and candidate boundaries. */
class PublicPageController extends Controller
{
    public function home()
    {
        $jobs = JobPosting::query()->forPublic()->where('job_postings.status', 'published')
            ->whereNull('job_postings.unavailable_at')
            ->leftJoin('job_published_profiles as dates', 'dates.job_posting_id', '=', 'job_postings.id')
            ->select('job_postings.*')
            ->orderByRaw('COALESCE(dates.published_at, job_postings.published_at, job_postings.created_at) DESC')
            ->orderByDesc('job_postings.id')->with('company:id,name')->limit(6)->get();

        return response()->view('public.home', compact('jobs'))->header('Cache-Control', 'private, no-store');
    }

    public function preferences(Request $request)
    {
        $query = $this->query($request);

        return $this->redirect($query ? route('query.preferences.edit', ['userQuery' => $query->public_id, ...$this->context($request)]) : route('jobs.start', ['guide' => 'preferences']));
    }

    public function compare(Request $request, JobSelectionUseCaseService $selection)
    {
        $query = $this->query($request);
        if (! $query) {
            return $this->redirect(route('jobs.start', ['guide' => 'compare']));
        }
        $context = $this->context($request);
        $ids = $request->query('jobs', []);
        if (is_array($ids) && array_is_list($ids) && count($ids) >= 2 && count($ids) <= 3
            && count(array_filter($ids, fn ($id) => is_string($id) && ctype_digit($id) && strlen($id) <= 16 && (int) $id > 0)) === count($ids)) {
            try {
                $selection->run($query, array_map('intval', $ids));

                return $this->redirect(route('query.jobs.compare', ['userQuery' => $query->public_id, 'jobs' => $ids, ...$context]));
            } catch (ModelNotFoundException|InvalidArgumentException) {
                // Deleted, unpublished or incompatible selections return to a useful entry point.
            }
        }

        return response()->view('public.compare', [
            'queryId' => $query->public_id, 'context' => $context,
            'invalidSelection' => $ids !== [],
            'listUrl' => route('query.jobs', ['userQuery' => $query->public_id, ...$context]),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function job(Request $request, string $job, JobSelectionUseCaseService $selection)
    {
        $published = strlen($job) <= 16 ? JobPosting::query()->forPublic()->where('status', 'published')->whereNull('unavailable_at')->find($job) : null;
        if (! $published) {
            return $this->redirect(route('jobs.start', ['guide' => 'unavailable']));
        }
        $query = $this->query($request);
        if ($query) {
            try {
                $selection->run($query, [(int) $published->id]);

                return $this->redirect(route('query.jobs.show', ['userQuery' => $query->public_id, 'job' => $published->id, ...$this->context($request)]));
            } catch (ModelNotFoundException|InvalidArgumentException) {
                // A new search may be needed for this occupation. Never bypass candidate access.
            }
        }

        return $this->redirect(route('jobs.start', ['job' => $published->id]));
    }

    public function company(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return $this->redirect(route('company.register'));
        }
        if ($user->companies()->wherePivotIn('role', ['company_owner', 'company_editor'])->exists()) {
            return $this->redirect(route('company.dashboard'));
        }
        if ($user->isPlatformOwner()) {
            return $this->redirect(route('admin.job-reviews.index'));
        }

        return response()->view('public.company')->header('Cache-Control', 'private, no-store');
    }

    private function query(Request $request): ?UserQuery
    {
        $tokens = [];
        foreach ($request->session()->all() as $key => $token) {
            if (str_starts_with($key, 'jobdd_query_token_') && Str::isUuid($id = substr($key, 18)) && is_string($token) && $token !== '') {
                $tokens[$id] = $token;
            }
        }
        $preferred = $request->query('query');
        if ($preferred !== null) {
            if (! is_string($preferred) || ! isset($tokens[$preferred])) {
                return null;
            }
            $tokens = [$preferred => $tokens[$preferred]];
        }
        if (! $tokens) {
            return null;
        }
        foreach (UserQuery::whereIn('public_id', array_keys($tokens))->orderByDesc('id')->get() as $query) {
            if (is_string($query->session_token) && $query->session_token !== '' && hash_equals($query->session_token, $tokens[$query->public_id])
                && in_array($query->occupation, JobSearchController::OCCUPATIONS, true)
                && ($query->region === null || in_array($query->region, JobSearchController::REGIONS, true))) {
                return $query;
            }
        }

        return null;
    }

    private function context(Request $request): array
    {
        $tools = $request->query('tools', []);
        $tools = is_array($tools) ? array_values(array_intersect(array_keys(JobDecisionUseCaseService::TOOLS), array_filter($tools, 'is_string'))) : [];
        $page = filter_var($request->query('page', 1), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => intdiv(PHP_INT_MAX, 20)]]);

        return ['page' => $page ?: 1, 'tools' => $tools];
    }

    private function redirect(string $url)
    {
        return redirect($url)->header('Cache-Control', 'private, no-store');
    }
}
