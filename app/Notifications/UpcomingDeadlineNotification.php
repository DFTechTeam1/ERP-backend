<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UpcomingDeadlineNotification extends Notification
{
    use Queueable;

    private $payload;

    private $lineIds;

    /**
     * Create a new notification instance.
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
        $this->lineIds = [$payload['employee']['employee']['line_id']];
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [
            \App\Notifications\LineChannel::class
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->line('The introduction to the notification.')
                    ->action('Notification Action', url('/'))
                    ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }

    public function toLine($notifiable)
    {
        $messages = [
            [
                'type' => 'text',
                'text' => 'Halo ' . $this->payload['employee']['employee']['nickname']. ', batas pengerjaan tugas ' . $this->payload['task']['name'] . ' adalah 2 hari lagi.',
            ],
            [
                'type' => 'text',
                'text' => 'Segera selesaikan ya. Semangat!'
            ],
            [
                'type' => 'sticker',
                'packageId' => '1070',
                'stickerId' => '17840',
            ]
        ];

        return [
            'line_ids' => $this->lineIds,
            'messages' => $messages,
        ];
    }
}