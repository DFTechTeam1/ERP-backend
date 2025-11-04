<?php

namespace Modules\Finance\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Finance\Models\ProjectDealPriceChange;

class NotifyRequestPriceChangesHasBeenApproved implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $changeId;

    protected string $type;

    /**
     * Create a new job instance.
     */
    public function __construct(string $changeId, string $type = 'approved')
    {
        $this->changeId = $changeId;
        $this->type = $type;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $change = ProjectDealPriceChange::with([
            'projectDeal',
            'requesterBy',
            'requesterBy.employee',
            'reason',
        ])->find($this->changeId);

        // here we should send notification to the requester
        if ($change) {
            $change->requesterBy->employee->notify(new \Modules\Finance\Notifications\NotifyRequestPriceChangesHasBeenApprovedNotification($change, $this->type));
        }
    }
}