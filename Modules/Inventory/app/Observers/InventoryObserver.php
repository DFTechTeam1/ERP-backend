<?php

namespace Modules\Inventory\Observers;

use Modules\Inventory\Models\Inventory;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class InventoryObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Handle when data is retrieved
     *
     * @param Employee $employee
     * @return void
     */
    public function retrieved(Inventory $inventory)
    {
        // if (!\Illuminate\Support\Facades\Cache::get('inventoriesCache')) {
        //     \App\Jobs\Cache\InventoriesCacheJob::dispatch();
        // }
    }

    /**
     * Handle the Inventory "created" event.
     */
    public function created(Inventory $inventory): void
    {
        //
    }

    /**
     * Handle the Inventory "updated" event.
     */
    public function updated(Inventory $inventory): void
    {
        //
    }

    /**
     * Handle the Inventory "deleted" event.
     */
    public function deleted(Inventory $inventory): void
    {
        //
    }

    /**
     * Handle the Inventory "restored" event.
     */
    public function restored(Inventory $inventory): void
    {
        //
    }

    /**
     * Handle the Inventory "force deleted" event.
     */
    public function forceDeleted(Inventory $inventory): void
    {
        //
    }
}
