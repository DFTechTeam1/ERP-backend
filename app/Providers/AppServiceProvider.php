<?php

namespace App\Providers;

use App\Events\TestingEvent;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local')) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        Broadcast::routes(['prefix' => 'api']);

        if (request()->header('App-Language')) {
            // avoid repetitive setting
            if (!\Illuminate\Support\Facades\App::isLocal(request()->header('App-Language'))) {
            }
            \Illuminate\Support\Facades\App::setLocale(request()->header('App-Language'));
        }

        // CACHING ALL SETTING
        if (\Illuminate\Support\Facades\DB::connection()->getDatabaseName() && Schema::hasTable('cache')) { // avoid failing down when start in the first time
            cachingSetting();


            // caching all data
            // if (!\Illuminate\Support\Facades\Cache::get(\App\Enums\Cache\CacheKey::InventoryList->value)) {
            //     \App\Jobs\Cache\InventoriesCacheJob::dispatch();
            // }

            // if (!\Illuminate\Support\Facades\Cache::get(\App\Enums\Cache\CacheKey::EmployeeList->value)) {
            //     \App\Jobs\Cache\EmployeerCacheJob::dispatch();
            // }
        }
    }
}
