<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Prometheus\CollectorRegistry;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PrometheusRequestMetrics
{
    private const START_ATTRIBUTE = 'prometheus_started_at';

    public function __construct(private readonly CollectorRegistry $registry) {}

    /**
     * Stamp the start time. Recording happens in terminate() so the status code
     * reflects the FINAL response, including exceptions (404, 405, 500) that the
     * kernel renders after the middleware stack has unwound.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(self::START_ATTRIBUTE, microtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $startedAt = $request->attributes->get(self::START_ATTRIBUTE);
        $duration = is_float($startedAt) ? microtime(true) - $startedAt : 0.0;

        $this->record($request, $response, $duration);
    }

    /**
     * Record one request as a counter increment and a duration observation.
     * Any failure here (e.g. a Redis blip) is swallowed so metrics never break a request.
     */
    private function record(Request $request, Response $response, float $durationSeconds): void
    {
        if (in_array($request->path(), config('prometheus.excluded_paths', []), true)) {
            return;
        }

        try {
            $namespace = config('prometheus.namespace');

            /** @var array<int, string> $labelValues */
            $labelValues = [
                $request->getMethod(),
                $this->routeLabel($request),
                (string) $response->getStatusCode(),
            ];

            $this->registry->getOrRegisterCounter(
                $namespace,
                'http_requests_total',
                'Total HTTP requests handled by Laravel.',
                ['method', 'route', 'status'],
            )->inc($labelValues);

            $this->registry->getOrRegisterHistogram(
                $namespace,
                'http_request_duration_seconds',
                'HTTP request duration in seconds.',
                ['method', 'route', 'status'],
                config('prometheus.buckets'),
            )->observe($durationSeconds, $labelValues);
        } catch (Throwable) {
            // Intentionally ignored: observability must never break the request path.
        }
    }

    /**
     * Use the route's URI pattern (with placeholders) as the label so cardinality
     * stays bounded. Unmatched requests (404s) collapse to a single series.
     */
    private function routeLabel(Request $request): string
    {
        return $request->route()?->uri() ?? '{unmatched}';
    }
}
