<?php

use Illuminate\Console\Scheduling\Schedule;
use Spatie\ScheduleMonitor\Models\MonitoredScheduledTaskLogItem;

it('schedules daily pruning of schedule-monitor log items', function () {
    $pruneEvent = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'model:prune')
            && str_contains($event->command ?? '', MonitoredScheduledTaskLogItem::class));

    expect($pruneEvent)->not->toBeNull();
    expect($pruneEvent->expression)->toBe('30 1 * * *');
});
