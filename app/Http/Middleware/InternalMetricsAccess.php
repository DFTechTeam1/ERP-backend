<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class InternalMetricsAccess
{
    /**
     * Allow the /metrics endpoint only from private networks (the Prometheus
     * scraper on the Docker network). Everything else gets a 404 so the endpoint
     * is not even discoverable from outside.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var array<int, string> $allowed */
        $allowed = config('prometheus.allowed_ips', []);

        if (! IpUtils::checkIp((string) $request->ip(), $allowed)) {
            abort(404);
        }

        return $next($request);
    }
}
