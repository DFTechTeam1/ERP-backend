<?php

namespace Modules\Finance\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Finance\Notifications\InvoiceHasBeenCreated;
use Modules\Finance\Repository\InvoiceRepository;

class InvoiceHasBeenCreatedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $invoiceId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $invoiceId)
    {
        $this->invoiceId = $invoiceId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $repo = new InvoiceRepository();

        $invoice = $repo->show(uid: $this->invoiceId, select: 'id,project_deal_id', relation: ['projectDeal:id,name']);

        $users = \App\Models\User::role(['finance', 'root'])
            ->get();

        $pusher = new \App\Services\PusherNotification;

        foreach ($users as $user) {
            $user->notify(new InvoiceHasBeenCreated($invoice->projectDeal));

            $pusher->send("my-channel-{$user->id}", 'notification-event', [
                'type' => 'finance',
                'reload' => 1
            ]);
        }
    }
}
