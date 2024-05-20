<?php

namespace Modules\Hrd\Observers;

use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Modules\Hrd\Models\Employee;

class EmployeeObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Handle when data is retrieved
     *
     * @param Employee $employee
     * @return void
     */
    public function retrieved(Employee $employee)
    {
        // if (!\Illuminate\Support\Facades\Cache::get('employeesCache')) {
        //     \App\Jobs\Cache\EmployeerCacheJob::dispatch();
        // }
    }

    /**
     * Handle the Employee "created" event.
     */
    public function created(Employee $employee): void
    {
        //
    }

    /**
     * Handle the Employee "updated" event.
     */
    public function updated(Employee $employee): void
    {
        //
    }

    /**
     * Handle the Employee "deleted" event.
     */
    public function deleted(Employee $employee): void
    {
        //
    }

    /**
     * Handle the Employee "restored" event.
     */
    public function restored(Employee $employee): void
    {
        //
    }

    /**
     * Handle the Employee "force deleted" event.
     */
    public function forceDeleted(Employee $employee): void
    {
        //
    }
}
