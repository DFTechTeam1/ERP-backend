<?php

namespace Modules\Production\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class ReviseTaskNotification extends Notification
{
    use Queueable;

    public $employee;

    public $task;

    public $revise;

    public $lineIds;

    /**
     * Create a new notification instance.
     */
    public function __construct($employee, $task, $revise)
    {
        $this->employee = $employee;

        $this->task = $task;

        $this->revise = $revise;

        $this->lineIds = [$employee->line_id];
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return [
            'database',
            \App\Notifications\LineChannel::class
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
            'title' => __('global.reviseTask'),
            'message' => __('global.newReviseTask', ['taskName' => $this->task->name]),
            'link' => config('app.frontend_url') . '/admin/production/project/' . $this->task->project->uid,
        ];
    }

    public function toLine($notifiable)
    {
        $messages = [
            [
                'type' => 'text',
                'text' => 'Halo ' . $this->employee->nickname . ' tugas ' . $this->task->name . ' di event ' . $this->task->project->name . ' harus di revisi nih.',
            ],
            [
                'type' => 'text',
                'text' => 'Revisinya karena ' . $this->revise->reason,
            ],
        ];

        return [
            'line_ids' => $this->lineIds,
            'messages' => $messages,
        ];
    }
}
