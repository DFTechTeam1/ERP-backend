<?php

use \Illuminate\Support\Facades\Cache;
use \App\Enums\Cache\CacheKey;

test('Total project cache has been updated', function () {
    $count = 3;
    \Modules\Production\Models\Project::factory()
        ->count(3)
        ->create();

    $cache = Cache::get(CacheKey::ProjectCount->value);

    expect($cache)->toBe($count + 1);
});
