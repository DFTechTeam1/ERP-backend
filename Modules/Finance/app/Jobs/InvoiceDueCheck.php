<?php

namespace Modules\Finance\Jobs;

use App\Services\GeneralService;
use App\Services\PusherNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Finance\Notifications\InvoiceDueCheckNotification;

class InvoiceDueCheck implements ShouldQueue
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

        $invoices = $generalService->getInvoiceDueData();

        $users = \App\Models\User::role(['root', 'finance'])
            ->with([
                'employee:id,name',
            ])
            ->get();

        // get related marketings and merge to existing users
        foreach ($invoices as $invoice) {
            foreach ($invoice->projectDeal->marketings as $marketing) {
                $marketingUser = \App\Models\User::with(['employee:id,name'])
                    ->where('employee_id', $marketing->employee_id)->first();

                $users->push($marketingUser);
            }
        }

        $pusher = new PusherNotification;

        foreach ($users as $user) {
            if ($invoices->count() > 0) {
                $user->notify(new InvoiceDueCheckNotification($invoices, $user));

                sleep(1);
                $pusher->send(
                    channel: "my-channel-{$user->id}",
                    event: 'notification-event',
                    payload: [
                        'type' => 'finance',
                    ]
                );
            }
        }
    }
}
