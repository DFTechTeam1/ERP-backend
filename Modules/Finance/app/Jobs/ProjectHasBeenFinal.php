<?php

namespace Modules\Finance\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Finance\Notifications\ProjectHasBeenFinal as NotificationsProjectHasBeenFinal;
use Modules\Production\Repository\ProjectDealRepository;

class ProjectHasBeenFinal implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $projectDealId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $projectDealId)
    {
        $this->projectDealId = $projectDealId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $repo = new ProjectDealRepository();

        $projectDeal = $repo->show(uid: $this->projectDealId, select: 'id,name,project_date');

        $users = \App\Models\User::role(['finance', 'root'])->get();

        foreach ($users as $user) {
            $user->notify(new NotificationsProjectHasBeenFinal(projectDeal: $projectDeal));
        }
    }
}
