<?php

namespace App\Providers;

use App\Http\Controllers\MetricsController;
use App\Http\Middleware\InternalMetricsAccess;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\Redis;

class PrometheusServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            CollectorRegistry::class,
            fn (): CollectorRegistry => new CollectorRegistry($this->storageAdapter(), false),
        );
    }

    public function boot(): void
    {
        Route::middleware(InternalMetricsAccess::class)
            ->get('/metrics', MetricsController::class)
            ->name('prometheus.metrics');
    }

    private function storageAdapter(): Adapter
    {
        if (config('prometheus.storage_adapter') === 'memory') {
            return new InMemory;
        }

        Redis::setDefaultOptions(config('prometheus.redis'));

        return new Redis;
    }
}
