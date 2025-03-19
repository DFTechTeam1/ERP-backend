<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Modules\Hrd\Console\SynchronizingTalentUserId;
use Modules\Production\Console\ClearAllCache;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command(ClearAllCache::class)->dailyAt('00.10');

Schedule::command(SynchronizingTalentUserId::class)->cron('1 1,2,3 * * *');

\Illuminate\Support\Facades\Schedule::call(new \App\Schedules\PostNotifyCompleteProject)->dailyAt('00:01');

\Illuminate\Support\Facades\Schedule::call(new \App\Schedules\UpcomingDeadlineTask)->dailyAt('09:00');

\Illuminate\Support\Facades\Schedule::command(\App\Console\Commands\pruneInteractiveAsset::class)->everyMinute();
