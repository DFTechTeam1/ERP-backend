<?php

use App\Console\Commands\ClearLogSchedule;
use App\Jobs\ProjectDealSummaryJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Modules\Finance\Jobs\InvoiceDueCheck;
use Modules\Hrd\Console\CheckEmployeeResign;
use Modules\Hrd\Console\SynchronizingTalentUserId;
use Modules\Hrd\Console\UpdateEmployeeActivePerMonth;
use Modules\Production\Console\ClearAllCache;
use Modules\Production\Console\PaymentDueReminderCommand;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command(ClearAllCache::class)->dailyAt('00.10');

Schedule::command(SynchronizingTalentUserId::class)->cron('1 1,2,3 * * *');

// clear logs
Schedule::command(ClearLogSchedule::class)->dailyAt('01:00');

\Illuminate\Support\Facades\Schedule::call(new \App\Schedules\PostNotifyCompleteProject)->dailyAt('00:01');

\Illuminate\Support\Facades\Schedule::call(new \App\Schedules\UpcomingDeadlineTask)->dailyAt('09:00');

\Illuminate\Support\Facades\Schedule::command(\App\Console\Commands\pruneInteractiveAsset::class)->everyMinute();

\Illuminate\Support\Facades\Schedule::command(UpdateEmployeeActivePerMonth::class)->lastDayOfMonth('23:00');

// Schedule::command('telescope:prune --hours=72')->daily();

Schedule::command(CheckEmployeeResign::class)->dailyAt('00:15');

Schedule::command(PaymentDueReminderCommand::class)->dailyAt('06:00');

Schedule::job(InvoiceDueCheck::class)->dailyAt('06:00');

Schedule::job(ProjectDealSummaryJob::class)->dailyAt('15:00');
