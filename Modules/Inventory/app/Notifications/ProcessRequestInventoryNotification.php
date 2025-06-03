<?php

namespace Modules\Inventory\Notifications;

use App\Notifications\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProcessRequestInventoryNotification extends Notification
{
    use Queueable;

    private $chatId;

    private $message;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $chatId, string $message)
    {
        $this->chatId = $chatId;
        $this->message = $message;
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
        return [
            'chatIds' => [$this->chatId],
            'message' => $this->message,
        ];
    }
}
