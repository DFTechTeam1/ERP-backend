<?php

namespace Modules\Production\Notifications;

use App\Notifications\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RemovePicFromSongNotification extends Notification
{
    use Queueable;

    public string $message;

    public string $projectUid;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $message, string $projectUid)
    {
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
            'title' => 'Removed as PIC from Song',
            'message' => $this->message,
            'button' => null,
            'href' => '/admin/production/project/' . $this->projectUid,
        ];
    }
}
