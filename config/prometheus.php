<?php

return [
    /*
     * Metric name prefix, e.g. "laravel" -> laravel_http_requests_total.
     */
    'namespace' => env('PROMETHEUS_NAMESPACE', 'laravel'),

    /*
     * Storage for the collected metrics:
     *   - "redis"  : shared across FrankenPHP workers (production/staging/dev).
     *   - "memory" : per-process only, used by the test suite.
     */
    'storage_adapter' => env('PROMETHEUS_STORAGE_ADAPTER', 'redis'),

    /*
     * The Prometheus client keeps its OWN phpredis connection (separate from
     * Laravel's), so the connection details are repeated here. Keys mirror the
     * app Redis env. Metric keys are namespaced with a "PROMETHEUS_" prefix, so
     * they never collide with cache/queue keys in the same Redis database.
     */
    'redis' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => (int) env('REDIS_PORT', 6379),
        'password' => env('REDIS_PASSWORD'),
        'timeout' => 0.5,
        'read_timeout' => 10,
        'persistent_connections' => false,
    ],

    /*
     * Request-duration histogram buckets, in seconds.
     */
    'buckets' => [0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10],

    /*
     * Request paths that are NOT counted (the scrape endpoint and health check).
     */
    'excluded_paths' => ['metrics', 'up'],

    /*
     * Private CIDRs permitted to read /metrics. Defence in depth: the edge nginx
     * also hides /metrics from the public backend vhost, and Prometheus scrapes
     * the container directly over the internal Docker network.
     */
    'allowed_ips' => ['127.0.0.1', '::1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'],
];
