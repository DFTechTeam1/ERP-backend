<?php

namespace Modules\Finance\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RequestInvoiceChangesNotification extends Notification
{
    use Queueable;

    private $payloadData;

    private $telegramIds;

    private $telegramMessage;

    /**
     * Create a new notification instance.
     */
    public function __construct(array $payloadData, array $telegramIds, array $telegramMessage)
    {
        $this->payloadData = $payloadData;

        $this->telegramIds = $telegramIds;

        $this->telegramMessage = $telegramMessage;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return [
            'mail',
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        setEmailConfiguration();

        return (new MailMessage)
            ->subject('Approval Required: Invoice Data Modification by '.$this->payloadData['actor']['employee']['name'])
            ->markdown('mail.invoice.changeRequest', $this->payloadData);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [];
    }

    public function toTelegram($notifiable): array
    {
        return [
            'chatIds' => $this->telegramIds,
            'message' => $this->telegramMessage,
        ];
    }
}