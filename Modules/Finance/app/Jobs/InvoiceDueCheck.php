<?php

namespace Modules\Finance\Jobs;

use App\Models\User;
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
        $generalService = app(GeneralService::class);

        $invoices = $generalService->getInvoiceDueData();

        $users = User::role(['root', 'finance'])
            ->with([
                'employee:id,name',
            ])
            ->get();

        // get related marketings and merge to existing users
        foreach ($invoices as $invoice) {
            foreach ($invoice->projectDeal->marketings as $marketing) {
                $marketingUser = User::with(['employee:id,name'])
                    ->where('employee_id', $marketing->employee_id)->first();

                // A marketing may have no user account; skip it so the notify loop
                // below never calls notify() on null.
                if ($marketingUser) {
                    $users->push($marketingUser);
                }
            }
        }

        $pusher = app(PusherNotification::class);

        foreach ($users as $user) {
            if (! $user) {
                continue;
            }

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
