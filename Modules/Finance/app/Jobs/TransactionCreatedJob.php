<?php

namespace Modules\Finance\Jobs;

use App\Services\PusherNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;

class TransactionCreatedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $transactionId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $transactionId)
    {
        $this->transactionId = $transactionId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $transaction = \Modules\Finance\Models\Transaction::with([
            'projectDeal:id,name,project_date,is_fully_paid',
            'projectDeal.transactions',
            'projectDeal.finalQuotation',
            'invoice:id,payment_due,uid',
            'attachments:id,transaction_id,image',
        ])
            ->where('id', $this->transactionId)
            ->first();

        $remainingBalance = $transaction->projectDeal->getRemainingPayment();
        $projectDealUid = Crypt::encryptString($transaction->projectDeal->id);

        // get role finance
        $users = \App\Models\User::role(['finance', 'root'])->get();

        $pusher = new PusherNotification;

        foreach ($users as $user) {
            $user->notify(new \Modules\Production\Notifications\TransactionCreatedNotification($transaction, $remainingBalance, $projectDealUid));

            $pusher->send(channel: "my-channel-{$user->id}", event: 'notification-event', payload: [
                'type' => 'finance',
            ]);
        }
    }
}
