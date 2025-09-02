<?php

namespace Modules\Production\Jobs;

use App\Services\GeneralService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Production\Notifications\PaymentDueReminderNotification;

class PaymentDueReminderJob implements ShouldQueue
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
     */
    public function handle(): void
    {
        $generalService = new GeneralService;

        $data = $generalService->getUpcomingPaymentDue();

        foreach ($data as $deal) {
            foreach ($deal->marketings as $marketing) {
                $marketing->employee->notify(new PaymentDueReminderNotification($deal));
            }
        }
    }
}
