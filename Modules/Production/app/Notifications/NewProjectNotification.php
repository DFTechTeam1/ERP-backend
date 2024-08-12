<?php

namespace Modules\Production\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class NewProjectNotification extends Notification
{
    use Queueable;

    public $project;

    public $employee;

    public $lineIds;

    /**
     * Create a new notification instance.
     */
    public function __construct($project, $employee)
    {
        $this->project = $project;

        $this->employee = $employee;

        $this->lineIds = [$this->employee->line_id];
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
        // set link so user can see the detailed of content on the front end
        $link = "/admin/production/project/{$this->project['uid']}";

        return [
            'title' => __('global.newProject'),
            'message' => __('global.newProjectNotification', ['event' => $this->project['name']]),
            'link' => $link,
        ];
    }

    public function toLine($notifiable)
    {
        $messages = [
            [
                'type' => 'text',
                'text' => 'Halo ' . $this->employee->nickname . ". Ada event baru nih buat kamu. Event " . $this->project->name . " di tanggal " . date('d F Y', strtotime($this->project->project_date)) . ' akan kamu handle.'
            ]
        ];

        return [
            'line_ids' => $this->lineIds,
            'messages' => $messages,
        ];
    }
}
