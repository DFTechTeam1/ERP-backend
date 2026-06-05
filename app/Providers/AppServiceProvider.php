<?php

namespace App\Providers;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        // Behind the docker edge nginx, internally-triggered URL generation (e.g.
        // MCP/Express calling laravel:8000) has no public Host, so route()/
        // signedRoute() would emit http://localhost:8000. Pin absolute URLs to the
        // public APP_URL in the hosted environments. Dev is left on the request
        // host (served as *.localhost:8080, which APP_URL does not match).
        if (app()->environment(['production', 'staging'])) {
            URL::forceRootUrl(config('app.url'));
            URL::forceScheme('https');
        }

        Broadcast::routes(['prefix' => 'api']);

        if (request()->header('App-Language')) {
            // avoid repetitive setting
            if (! App::isLocal(request()->header('App-Language'))) {
            }
            App::setLocale(request()->header('App-Language'));
        }

        // CACHING ALL SETTING
        try {
            if (Schema::hasTable('cache')) {
                cachingSetting();
            }
        } catch (\Exception $e) {
            cachingSetting();
            // Database not available yet, skip
            // Log::warning('Cache table check failed: ' . $e->getMessage());
        }
    }
}
