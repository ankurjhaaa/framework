<?php

namespace Kite\Core\Middleware;

use Kite\Core\Request;
use Kite\Core\Session;
use Closure;

class VerifyCsrfToken
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Only validate write requests
        if ($this->isReading($request)) {
            return $next($request);
        }

        // Get token from Session
        $sessionToken = Session::instance()->get('_token');

        // Check token from POST payload or Header
        $token = $request->input('_token') ?: $request->header('X-Csrf-Token');

        // If tokens match, allow the request to proceed
        if (is_string($sessionToken) && is_string($token) && hash_equals($sessionToken, $token)) {
            return $next($request);
        }

        // Throw a 419 Page Expired Error
        abort(419, 'Page Expired (CSRF Token Mismatch). Please refresh and try again.');
    }

    /**
     * Determine if the HTTP request uses a "read" verb.
     */
    protected function isReading(Request $request): bool
    {
        return in_array($request->method, ['HEAD', 'GET', 'OPTIONS']);
    }
}
