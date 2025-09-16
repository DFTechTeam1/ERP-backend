<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\ProjectDealSummaryNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProjectDealSummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     * Sending summary to finance
     */
    public function handle(): void
    {
        $users = User::role(['finance', 'root'])->get();
        logging('USERS SUMMARY DEAL', $users->toArray());
        foreach ($users as $user) {
            $user->notify(new ProjectDealSummaryNotification);
        }
    }
}
