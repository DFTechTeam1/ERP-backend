<?php

namespace Modules\Production\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class ChangedSongNotification extends Notification
{
    use Queueable;

    public $telegramChatIds;

    public $message;

    public $projectUid;

    /**
     * Create a new notification instance.
     */
    public function __construct(array $telegramChatIds, mixed $message, string $projectUid)
    {
        $this->telegramChatIds = $telegramChatIds;
        $this->message = $message;
        $this->projectUid = $projectUid;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return [
            'database'
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
            'type' => 'production',
            'title' => 'Song Edit Request Added',
            'message' => $this->message,
            'button' => null,
            'href' => '/admin/production/project/' . $this->projectUid,
        ];
    }

    public function toTelegram($notifiable): array
    {
        return [
            'chatIds' => $this->telegramChatIds,
            'message' => $this->message,
        ];
    }
}
