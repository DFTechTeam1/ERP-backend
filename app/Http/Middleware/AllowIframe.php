<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AllowIframe
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is('preview-mail*')) {
            $response->headers->remove('X-Frame-Options');

            return $response->header(
                'Content-Security-Policy',
                "frame-ancestors 'self' http://localhost:5174"
            );
        }

        return $response;
    }
}
