<?php

namespace Modules\Finance\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Finance\Notifications\InvoiceHasBeenCreated;
use Modules\Finance\Repository\InvoiceRepository;

class InvoiceHasBeenCreatedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly string $invoiceId,
        public readonly ?Authenticatable $user = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $repo = new InvoiceRepository;

        $invoice = $repo->show(
            uid: $this->invoiceId,
            select: 'id,project_deal_id,number,amount,payment_due',
            relation: ['projectDeal:id,name']
        );

        $users = \App\Models\User::role(['finance', 'root'])
            ->get();

        $pusher = new \App\Services\PusherNotification;

        foreach ($users as $user) {
            $user->notify(new InvoiceHasBeenCreated($invoice->projectDeal));

            $pusher->send("my-channel-{$user->id}", 'notification-event', [
                'type' => 'finance',
            ]);
        }

        $developer = \App\Models\User::where('email', config('app.developer_email'))->first();
        if ($developer) {
            $actor = \Modules\Hrd\Models\Employee::select('nickname')->find($this->user?->employee_id);
            $developer->notify(new InvoiceHasBeenCreated(
                projectDeal: $invoice->projectDeal,
                eventName: $invoice->projectDeal->name,
                invoiceNumber: $invoice->number,
                totalAmount: "Rp" . number_format($invoice->amount, 0, ',', '.'),
                dueDate: $invoice->payment_due ? date('d F Y H:i', strtotime($invoice->payment_due)) : null,
                issuedAt: date('d F Y H:i', strtotime($invoice->created_at)),
                actorName: $actor ? $actor->nickname : null,
                paymentStatus: 'Unpaid',
            ));
        }
    }
}