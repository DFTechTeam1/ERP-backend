<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TelescopeMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Example: Allow only authenticated API users to access Telescope
        if ($request->user()) {
            return $next($request);
        }

        return abort(403, 'Unauthorized access to Telescope');
    }
}
