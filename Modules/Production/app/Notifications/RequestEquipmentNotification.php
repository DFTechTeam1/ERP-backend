<?php

namespace Modules\Production\Notifications;

use App\Notifications\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RequestEquipmentNotification extends Notification
{
    use Queueable;

    public $users;

    public $messages;

    /**
     * Create a new notification instance.
     */
    public function __construct($users, $messages)
    {
        $this->users = $users;

        $this->messages = $messages;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return [
            TelegramChannel::class,
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
        $telegramChatIds = collect($this->users)->pluck('telegram_chat_id')->toArray();
        $telegramChatIds = array_values(array_filter($telegramChatIds));

        return [
            'chatIds' => $telegramChatIds,
            'message' => collect($this->messages)->pluck('text')->toArray(),
        ];
    }

    public function toLine($notifiable): array
    {
        $lineIds = collect($this->users)->pluck('line_id')->toArray();
        $lineIds = array_values(array_filter($lineIds));

        return [
            'line_ids' => $lineIds,
            'messages' => $this->messages,
        ];
    }
}
