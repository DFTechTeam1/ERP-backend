<?php

use App\Console\Commands\ClearLogSchedule;
use App\Console\Commands\pruneInteractiveAsset;
use App\Console\Commands\PruneRefreshTokens;
use App\Jobs\ProjectDealSummaryJob;
use App\Schedules\PostNotifyCompleteProject;
use App\Schedules\UpcomingDeadlineTask;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Modules\Finance\Jobs\InvoiceDueCheck;
use Modules\Hrd\Console\CheckEmployeeResign;
use Modules\Hrd\Console\CheckTransferEntityScheduleCommand;
use Modules\Hrd\Console\SynchronizingTalentUserId;
use Modules\Hrd\Console\UpdateEmployeeActivePerMonth;
use Modules\Production\Console\ClearAllCache;
use Modules\Production\Console\PaymentDueReminderCommand;
use Modules\Production\Console\ResyncNasFolderCreation;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command(ClearAllCache::class)->dailyAt('00.10');

Schedule::command(SynchronizingTalentUserId::class)->cron('1 1,2,3 * * *');

// clear logs
Schedule::command(ClearLogSchedule::class)->dailyAt('01:00');

// prune long-expired refresh tokens
Schedule::command(PruneRefreshTokens::class)->dailyAt('02:00');

Schedule::call(new PostNotifyCompleteProject)->dailyAt('00:01');

Schedule::call(new UpcomingDeadlineTask)->dailyAt('09:00');

Schedule::command(pruneInteractiveAsset::class)->everyMinute();

Schedule::command(UpdateEmployeeActivePerMonth::class)
    ->lastDayOfMonth('23:00')
    ->runInBackground();

Schedule::command(ResyncNasFolderCreation::class)
    ->twiceDailyAt(9, 16)
    ->runInBackground();

// \Illuminate\Support\Facades\Schedule::job

// Schedule::command('telescope:prune --hours=72')->daily();

Schedule::command(CheckTransferEntityScheduleCommand::class)
    ->dailyAt('00:01')
    ->runInBackground();

// deactivate employees whose resignation date is today
Schedule::command(CheckEmployeeResign::class)
    ->dailyAt('00:05')
    ->runInBackground();

Schedule::command(PaymentDueReminderCommand::class)
    ->dailyAt('06:00')
    ->runInBackground();

Schedule::job(InvoiceDueCheck::class)->dailyAt('06:00');

Schedule::job(ProjectDealSummaryJob::class)->dailyAt('15:00');
