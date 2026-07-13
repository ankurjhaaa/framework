<?php

namespace Kite\Core\Middleware;

use Kite\Core\Request;
use Kite\Core\Auth;
use Closure;

class Permission
{
    /**
     * Handle an incoming request.
     * 
     * @param string $permission The required permission codename (e.g. 'add_post')
     */
    public function handle(Request $request, Closure $next, string $permission)
    {
        // First, ensure the user is logged in
        if (!Auth::check()) {
            if ($request->isAjax()) {
                return json(['error' => 'Unauthenticated.'], 401);
            }
            return redirect(route('login'));
        }

        // Check if the user has the required permission
        if (!Auth::hasPerm($permission)) {
            if ($request->isAjax()) {
                return json(['error' => 'Forbidden. You do not have permission to perform this action.'], 403);
            }
            abort(403, 'Forbidden. You do not have permission to access this page.');
        }

        // Proceed if allowed
        return $next($request);
    }
}
