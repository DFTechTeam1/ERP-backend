<?php

namespace Modules\Production\Notifications;

use App\Notifications\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class TaskIsCompleteNotification extends Notification
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
        return [
            'chatIds' => $this->telegramChatIds,
            'message' => 'Halo ' . $this->employee->nickname . ". Tugas " . $this->task->name . " sudah selesai dan sudah tidak ada kesalahan. Kamu bisa melanjutkan tugas yang lain. SEMANGAT :)",
        ];
    }

    public function toLine($notifiable)
    {
        $messages = [
            [
                'type' => 'text',
                'text' => 'Halo ' . $this->employee->nickname . ". Tugas " . $this->task->name . " sudah selesai dan sudah tidak ada kesalahan. Kamu bisa melanjutkan tugas yang lain. SEMANGAT :)",
            ],
        ];

        return [
            'line_ids' => [],
            'messages' => $messages,
        ];
    }
}
