<?php

namespace Modules\Production\Notifications;

use App\Notifications\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RemovePicFromSongNotification extends Notification
{
    use Queueable;

    public $taskSong;

    /**
     * Create a new notification instance.
     */
    public function __construct(object $taskSong)
    {
        $this->taskSong = $taskSong;
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
        $message = "You have been removed as PIC for the song '{$this->taskSong->song->name}' in project '{$this->taskSong->project->name}'.";
        return [
            'type' => 'production',
            'title' => 'Removed as PIC from Song',
            'message' => $message,
            'button' => null,
            'href' => '/admin/production/project/' . $this->taskSong->project->uid,
        ];
    }

    public function toTelegram($notifiable): array
    {
        $telegramChatIds = [
            $this->taskSong->employee->telegram_chat_id,
        ];

        $message = "Halo {$this->taskSong->employee->nickname}";
        $message .= "\nTugas JB {$this->taskSong->song->name} di event {$this->taskSong->project->name} tidak jadi kamu kerjakan ya. :)";

        return [
            'chatIds' => $telegramChatIds,
            'message' => $message,
        ];
    }
}
