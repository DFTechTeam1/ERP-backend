<?php

namespace Modules\Production\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Production\Notifications\PaymentDueReminderNotification;

class PaymentDueReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $projectDeals;

    /**
     * Create a new job instance.
     */
    public function __construct(Collection $projectDeals)
    {
        $this->projectDeals = $projectDeals;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        foreach ($this->projectDeals as $deal) {
            foreach ($deal->marketings as $marketing) {
                $marketing->employee->notify(new PaymentDueReminderNotification($deal));
            }
        }
    }
}
