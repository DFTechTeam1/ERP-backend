<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
        Broadcast::routes(['prefix' => 'api']);

        if (request()->header('App-Language')) {
            // avoid repetitive setting
            if (! \Illuminate\Support\Facades\App::isLocal(request()->header('App-Language'))) {
            }
            \Illuminate\Support\Facades\App::setLocale(request()->header('App-Language'));
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
