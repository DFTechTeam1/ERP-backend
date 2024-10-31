<?php

namespace Modules\Production\Notifications;

use App\Notifications\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class RemovePMFromProjectNotification extends Notification
{
    use Queueable;

    public $project;

    public $employee;

    public $telegramChatIds;

    /**
     * Create a new notification instance.
     */
    public function __construct($project, $employee)
    {
        $this->employee = $employee;

        $this->project = $project;

        $this->telegramChatIds = [$this->employee->telegram_chat_id];
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return [
            'database',
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
        // set link so user can see the detailed of content on the front end
        $link = '';

        return [
            'title' => __('notification.youAreRemovedFromProject', ['project' => $this->project->name]),
            'message' => __('noticiation.youAreRemovedFromProjectText', ['project' => $this->project->name]),
            'link' => $link,
        ];
    }

    public function toTelegram($notifiable): array
    {
        return [
            'chatIds' => $this->telegramChatIds,
            'message' => 'Halo ' . $this->employee->nickname . ", Event {$this->project->name} tidak jadi kamu kerjakan. Mungkin akan di ganti dengan project yang lebih besar :) Semangat!",
        ];
    }

    public function toLine($notifiable)
    {
        $messages = [
            [
                'type' => 'text',
                'text' => 'Halo ' . $this->employee->nickname . ", Event {$this->project->name} tidak jadi kamu kerjakan. Mungkin akan di ganti dengan project yang lebih besar :) Semangat!",
            ]
        ];

        return [
            'line_ids' => [],
            'messages' => $messages,
        ];
    }
}
