<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

\Illuminate\Support\Facades\Schedule::call(new \App\Schedules\PostNotifyCompleteProject)->dailyAt('00:01');

\Illuminate\Support\Facades\Schedule::call(new \App\Schedules\UpcomingDeadlineTask)->dailyAt('09:00');
