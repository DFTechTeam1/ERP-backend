<?php

namespace Modules\Finance\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Finance\Models\ProjectDealPriceChange;

class NotifyRequestPriceChangesHasBeenApproved implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $changeId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $changeId)
    {
        $this->changeId = $changeId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $change = ProjectDealPriceChange::with([
            'projectDeal',
            'requesterBy'
        ])->find($this->changeId);

        // here we should send notification to the requester
        if ($change) {
            $change->requesterBy->notify(new \Modules\Finance\Notifications\NotifyRequestPriceChangesHasBeenApprovedNotification($change));
        }
    }
}
