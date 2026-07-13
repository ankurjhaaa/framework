<?php

namespace Kite\Core\Middleware;

use Kite\Core\Request;
use Kite\Core\Auth;
use Closure;

class Authenticate
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // If user is not logged in, redirect them to login page
        if (!Auth::check()) {
            
            // If it's an AJAX/API request, return JSON error
            if ($request->isAjax()) {
                return json(['error' => 'Unauthenticated.'], 401);
            }
            
            // Standard redirect
            return redirect(route('login'));
        }

        // User is authenticated, proceed with the request
        return $next($request);
    }
}
