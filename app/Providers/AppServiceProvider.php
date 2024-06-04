<?php

namespace App\Providers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
    public function boot(): void
    {
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
