<?php

namespace Modules\Production\Notifications;

use App\Notifications\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

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
            TelegramChannel::class
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

    public function toTelegram($notifiable): array
    {
        $telegramChatIds = [
            $this->taskSong->employee->telegram_chat_id
        ];

        $message = "Halo {$this->taskSong->employee->nickname}";
        $message .= "\nTugas JB {$this->taskSong->song->name} di event {$this->taskSong->project->name} tidak jadi kamu kerjakan ya. :)";

        return [
            'chatIds' => $telegramChatIds,
            'message' => $message
        ];
    }
}
