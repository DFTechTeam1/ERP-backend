<?php

/**
 * This trait used to reset related cache when related model has been changed.
 */

namespace App\Traits;

use Illuminate\Support\Facades\Cache;

trait FlushCacheOnModelChange
{
    public static function bootFlushCacheOnModelChange()
    {
        static::saved(function ($model) {
            self::clearRelatedCache($model);
        });

        static::deleted(function ($model) {
            self::clearRelatedCache($model);
        });
    }

    protected static function clearRelatedCache($model)
    {
        $map = config('cache-dependencies');

        $class = get_class($model);
        if (isset($map[$class])) {
            foreach ($map[$class] as $cacheKey) {
                Cache::forget($cacheKey);
            }
        }
    }
}
