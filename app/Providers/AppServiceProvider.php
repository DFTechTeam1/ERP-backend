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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // CACHING ALL SETTING
        if (\Illuminate\Support\Facades\DB::connection()->getDatabaseName() && Schema::hasTable('cache')) { // avoid failing down when start in the first time
            $setting = Cache::get('setting');
    
            if (!$setting) {
                Cache::rememberForever('setting', function () {
                    $data = \Modules\Company\Models\Setting::get();
    
                    return $data->toArray();
                });
            }


            // caching all data
            if (!\Illuminate\Support\Facades\Cache::get(\App\Enums\Cache\CacheKey::InventoryList->value)) {
                \App\Jobs\Cache\InventoriesCacheJob::dispatch();
            }

            if (!\Illuminate\Support\Facades\Cache::get(\App\Enums\Cache\CacheKey::EmployeeList->value)) {
                \App\Jobs\Cache\EmployeerCacheJob::dispatch();
            }
        }
    }
}
