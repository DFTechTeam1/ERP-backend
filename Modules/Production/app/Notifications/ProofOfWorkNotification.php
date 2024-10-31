<?php

namespace Modules\Production\Notifications;

use App\Notifications\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class ProofOfWorkNotification extends Notification
{
    use Queueable;

    public $project;

    public $taskPic;

    public $task;

    public $telegramChatIds;

    /**
     * Create a new notification instance.
     */
    public function __construct($project, $taskPic, $task, $telegramChatIds)
    {
        $this->project = $project;
        $this->taskPic = $taskPic;
        $this->task = $task;
        $this->telegramChatIds = $telegramChatIds;
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
            'message' => "{$this->taskPic->nickname} baru saja menyelesaikan tugas {$this->task->name}. Silahkan login untuk melihat detailnya.",
        ];
    }

    public function toLine($notifiable)
    {
        $messages = [
            [
                'type' => 'text',
                'text' => "{$this->taskPic->nickname} baru saja menyelesaikan tugas {$this->task->name}. Silahkan login untuk melihat detailnya.",
            ]
        ];

        return [
            'line_ids' => [],
            'messages' => $messages,
        ];
    }
}
