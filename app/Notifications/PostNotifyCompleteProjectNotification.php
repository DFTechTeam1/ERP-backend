<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PostNotifyCompleteProjectNotification extends Notification
{
    use Queueable;

    private $payload;

    private $telegramChatIds;

    /**
     * Create a new notification instance.
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
        $this->telegramChatIds = [$payload['employee']['telegram_chat_id']];
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [
            TelegramChannel::class,
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

    public function toTelegram($notifiable): array
    {
        return [
            'chatIds' => $this->telegramChatIds,
            'message' => [
                'Halo '.$this->payload['employee']['nickname'].', event '.$this->payload['project']['name'].' sudah selesai dan kamu belum memberi penilaian pada sistem.',
                'Segera login dan beri penilaian ya :)',
            ],
        ];
    }

    public function toLine($notifiable)
    {
        $messages = [
            [
                'type' => 'text',
                'text' => 'Halo '.$this->payload['employee']['nickname'].', event '.$this->payload['project']['name'].' sudah selesai dan kamu belum memberi penilaian pada sistem.',
            ],
            [
                'type' => 'text',
                'text' => 'Segera login dan beri penilaian ya :)',
            ],
        ];

        return [
            'line_ids' => [],
            'messages' => $messages,
        ];
    }
}
