<?php

namespace Modules\Production\Notifications;

use App\Notifications\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RemoveUserFromTaskNotification extends Notification
{
    use Queueable;

    public $employee;

    public $task;

    public $telegramChatIds;

    /**
     * Create a new notification instance.
     */
    public function __construct($employee, $task)
    {
        $this->employee = $employee;

        $this->task = $task;

        $this->telegramChatIds = [$employee->telegram_chat_id];
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
            'chatIds' => $this->telegramChatIds,
            'message' => 'Halo '.$this->employee->nickname.', Tugas '.$this->task->name.' tidak jadi kamu kerjakan nih. Tunggu notifikasi lain untuk tugas yang akan kamu kerjakan ya :)',
        ];
    }

    public function toLine($notifiable)
    {
        $messages = [
            [
                'type' => 'text',
                'text' => 'Halo '.$this->employee->nickname.', Tugas '.$this->task->name.' tidak jadi kamu kerjakan nih. Tunggu notifikasi lain untuk tugas yang akan kamu kerjakan ya :)',
            ],
        ];

        return [
            'line_ids' => [],
            'messages' => $messages,
        ];
    }
}
