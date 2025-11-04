<?php

namespace Modules\Finance\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RejectInvoiceChangesNotification extends Notification
{
    use Queueable;

    private $invoice;

    /**
     * Create a new notification instance.
     */
    public function __construct(object $invoice)
    {
        $this->invoice = $invoice;
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

        return (new MailMessage)
            ->subject('Your invoice request #'.$this->invoice->invoice->parent_number.' has been rejected')
            ->markdown('mail.invoice.requestHasBeenRejected', [
                'invoice' => $this->invoice,
                'invoiceUrl' => '',
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