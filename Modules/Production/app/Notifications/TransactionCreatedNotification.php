<?php

namespace Modules\Production\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Modules\Finance\Models\Transaction;

class TransactionCreatedNotification extends Notification
{
    use Queueable;

    private $transaction;

    private $remainingBalance;

    /**
     * Create a new notification instance.
     */
    public function __construct(Transaction $transaction, mixed $remainingBalance)
    {
        $this->transaction = $transaction;

        $this->remainingBalance = $remainingBalance;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['mail'];
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
                'invoiceUrl' => url("invoices/download/{$this->transaction->uid}/stream"),
            ]);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [];
    }
}
