<?php

namespace Modules\Production\Notifications;

use App\Notifications\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CancelProjectWithPicNotification extends Notification
{
    use Queueable;

    public $employee;

    public $project;

    /**
     * Create a new notification instance.
     */
    public function __construct($employee, $project)
    {
        $this->employee = $employee;
        $this->project = $project;
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
        $link = null;

        return [
            'title' => __('global.projectCancelation'),
            'message' => __('global.projectCancelText', ['project' => $this->project->name]),
            'link' => $link,
        ];
    }

    public function toTelegram($notifiable): array
    {
        $eventDate = date('d F Y', strtotime($this->project->project_date));

        return [
            'chatIds' => [$this->employee->telegram_chat_id],
            'message' => "Halo {$this->employee->nickname}, event {$this->project->name} di tanggal {$eventDate} di cancel nih.",
        ];
    }

    public function toLine($notifiable)
    {
        $eventDate = date('d F Y', strtotime($this->project->project_date));
        $messages = [
            [
                'type' => 'text',
                'text' => "Halo {$this->employee->nickname}, event {$this->project->name} di tanggal {$eventDate} di cancel nih.",
            ],
        ];

        return [
            'line_ids' => [$this->employee->line_id],
            'messages' => $messages,
        ];

    }
}
