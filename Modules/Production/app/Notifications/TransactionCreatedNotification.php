<?php

namespace Modules\Production\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;
use Modules\Finance\Models\Transaction;

class TransactionCreatedNotification extends Notification
{
    use Queueable;

    private $transaction;

    private $remainingBalance;

    private $url;

    /**
     * Create a new notification instance.
     */
    public function __construct(Transaction $transaction, mixed $remainingBalance)
    {
        $this->transaction = $transaction;

        $this->remainingBalance = $remainingBalance;

        // make invoice url
        $this->url = URL::signedRoute(name: 'invoice.download', parameters: [
            'n' => \Illuminate\Support\Facades\Crypt::encryptString($this->transaction->invoice->id)
        ]);
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return [
            'mail',
            'database'
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        setEmailConfiguration();

        // define attachments
        $attachments = collect($this->transaction->attachments)->pluck('real_path')->toArray();
        
        return (new MailMessage)
            ->subject("New Transaction {$this->transaction->trx_id}")
            ->attachMany($attachments)
            ->markdown('mail.payment.transactionCreated', [
                'transaction' => $this->transaction,
                'remainingBalance' => $this->remainingBalance,
                'invoiceUrl' => $this->url,
            ]);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
         return [
            'type' => 'transaction_created',
            'title' => 'New transaction recorded',
            'message' => "A new transaction has been added the for project <b>{$this->transaction->projectDeal->name}</b>. Please review and validate the entry",
            'button' => [
                'text' => 'Download Invoice',
                'button' => $this->url
            ],
            'href' => null
        ];
    }
}
