<?php

namespace App\Support;

use App\Http\Controllers\JobSearchController;
use App\Models\UserQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PublicQueryNavigation
{
    /** Navigation values belong to this request only, never to a shared cache or session. */
    public static function context(Request $request): array
    {
        $key = self::class.'.context';
        if (! $request->attributes->has($key)) {
            $request->attributes->set($key, [
                'preferencesUrl' => self::preferencesUrl($request, self::query($request)),
            ]);
        }

        return $request->attributes->get($key);
    }

    public static function preferencesUrl(Request $request, ?UserQuery $query): string
    {
        if (! $query) {
            return route('public.preferences');
        }

        $context = $request->only(['page', 'tools', 'sort', 'return_job']);
        if ($request->routeIs('query.jobs.show')) {
            $context['return_job'] = $request->route('job');
        }

        return route('query.preferences.edit', ['userQuery' => $query->public_id, ...$context]);
    }

    public static function query(Request $request): ?UserQuery
    {
        $key = self::class.'.query';
        if (! $request->attributes->has($key)) {
            $request->attributes->set($key, self::resolveQuery($request));
        }

        return $request->attributes->get($key);
    }

    private static function resolveQuery(Request $request): ?UserQuery
    {
        $tokens = [];
        foreach ($request->session()->all() as $key => $token) {
            if (str_starts_with($key, 'jobdd_query_token_') && Str::isUuid($id = substr($key, 18)) && is_string($token) && $token !== '') {
                $tokens[$id] = $token;
            }
        }
        $routeQuery = $request->route('userQuery');
        $preferred = $routeQuery instanceof UserQuery ? $routeQuery->public_id : $request->query('query');
        if ($preferred !== null) {
            if (! is_string($preferred) || ! isset($tokens[$preferred])) {
                return null;
            }
            $tokens = [$preferred => $tokens[$preferred]];
        }
        if (! $tokens) {
            return null;
        }
        // Model binding already loaded the current query; navigation must not reload it.
        $queries = $routeQuery instanceof UserQuery
            ? [$routeQuery]
            : UserQuery::whereIn('public_id', array_keys($tokens))->orderByDesc('id')->get();
        foreach ($queries as $query) {
            if (is_string($query->session_token) && $query->session_token !== '' && hash_equals($query->session_token, $tokens[$query->public_id])
                && in_array($query->occupation, JobSearchController::OCCUPATIONS, true)
                && ($query->region === null || in_array($query->region, JobSearchController::REGIONS, true))) {
                return $query;
            }
        }

        return null;
    }
}
