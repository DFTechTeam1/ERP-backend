<?php

namespace Modules\Inventory\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class NewRequestInventoryNotification extends Notification
{
    use Queueable;

    public $data;

    public $requester;

    /**
     * Create a new notification instance.
     */
    public function __construct(array $data, object $requester)
    {
        $this->data = $data;
        $this->requester = $requester;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return [
            'database',
            \App\Notifications\LineChannel::class
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
        return [];
    }

    public function toLine($notifiable)
    {
        $message = $this->requester->nickname . " mengajukan pembelian barang baru seperti\n";

        $messages = [
            [
                'type' => 'flex',
                'body' => [
                    ''
                ],
            ]
        ];

        return [
            'line_ids' => $this->lineIds,
            'messages' => $messages,
        ];
    }
}
