<?php

namespace Modules\Production\Observers;

use Illuminate\Support\Facades\Log;
use Modules\Production\Models\NasFolderCreation;
use Modules\Production\Models\Project;

class NasFolderObserver
{
    /**
     * Handle the NasFolder "created" event.
     */
    public function created(Project $project): void
    {
        Log::debug('created', $project->toArray());
    }

    /**
     * Handle the NasFolder "updated" event.
     */
    public function updated(Project $project): void
    {
        Log::debug("updated project: ", $project->toArray());


    }

    /**
     * Handle the NasFolder "deleted" event.
     */
    public function deleted(Project $project): void
    {
        Log::debug('deleted project: ', $project->toArray());
    }

    /**
     * Handle the NasFolder "restored" event.
     */
    public function restored(NasFolder $nasfolder): void
    {
        //
    }

    /**
     * Handle the NasFolder "force deleted" event.
     */
    public function forceDeleted(NasFolder $nasfolder): void
    {
        //
    }
}
