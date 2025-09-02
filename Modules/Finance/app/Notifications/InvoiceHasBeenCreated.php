<?php

namespace Modules\Finance\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceHasBeenCreated extends Notification
{
    use Queueable;

    private $projectDeal;

    /**
     * Create a new notification instance.
     */
    public function __construct($projectDeal)
    {
        $this->projectDeal = $projectDeal;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return [
            'database',
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('The introduction to the notification.')
            ->action('Notification Action', 'https://laravel.com')
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'finalized',
            'title' => 'Invoice issued to customer',
            'message' => "An invoice has been created for project <b>{$this->projectDeal->name}</b>. Ensure it’s tracked and sent to the customer.",
            'button' => null,
            'href' => '/admin/deals/'.\Illuminate\Support\Facades\Crypt::encryptString($this->projectDeal->id),
        ];
    }
}
