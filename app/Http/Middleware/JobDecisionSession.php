<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Session\Middleware\StartSession;
use Symfony\Component\HttpFoundation\Cookie;

/** Keep decision GETs read-only, including the first visit to the input form. */
class JobDecisionSession extends StartSession
{
    public const FORM_COOKIE = 'jobdd_form_bootstrap';

    public function handle($request, Closure $next)
    {
        $entry = $request->routeIs('jobs.start', 'jobs.store');
        if (! $entry && ! $request->routeIs('home', 'public.*', 'query.jobs', 'query.jobs.show', 'query.jobs.compare', 'query.agencies', 'query.preferences.*')) {
            return parent::handle($request, $next);
        }

        $session = $this->startSession($request, $this->getSession($request));
        $request->setLaravelSession($session);
        if ($entry) {
            // EncryptCookies has authenticated this HttpOnly cookie. Restore only the CSRF
            // token of an otherwise empty session, never authorization or an existing session.
            $cookie = $request->cookie(self::FORM_COOKIE);
            $bootstrap = is_string($cookie) ? json_decode($cookie, true) : null;
            if (array_keys($session->all()) === ['_token'] && is_array($bootstrap)
                && ($bootstrap['id'] ?? null) === $session->getId()
                && is_int($bootstrap['expires'] ?? null) && $bootstrap['expires'] > time()
                && is_string($bootstrap['csrf'] ?? null) && strlen($bootstrap['csrf']) === 40) {
                $session->put('_token', $bootstrap['csrf']);
            }
        }

        // No garbage collection, lock or sliding lifetime refresh on decision GETs.
        $response = $next($request);
        if ($request->routeIs('jobs.start')) {
            $this->addCookieToResponse($response, $session);
            $config = $this->manager->getSessionConfig();
            $expires = time() + 1200;
            // Encrypted by the enclosing EncryptCookies middleware; no Query token is included.
            $response->headers->setCookie(new Cookie(self::FORM_COOKIE, json_encode([
                'id' => $session->getId(), 'csrf' => $session->token(), 'expires' => $expires,
            ]), $expires, $config['path'], $config['domain'], $config['secure'], true, false, 'lax'));
        } elseif ($request->routeIs('jobs.store')) {
            $this->addCookieToResponse($response, $session);
            $this->saveSession($request);
        }

        return $response;
    }
}
