<?php

namespace Modules\Production\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class NewProjectForEntertainmentNotification extends Notification
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

        if ($employee->line_id) {
            $this->lineIds = [$employee->line_id];
        }
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
            'title' => __('global.newProject'),
            'message' => __('global.newProjectNotificationEntertainment', ['name' => $this->project['name'], 'date' => date('d F Y', strtotime($this->project['project_date']))]),
            'link' => null,
        ];
    }

    public function toLine($notifiable)
    {
        if (count($this->lineIds)) {
            $messages = [
                [
                    'type' => 'text',
                    'text' => 'Halo ' . $this->employee->nickname . ". Ada event baru lagi nih. Ini Detailnya: \nevent: " . $this->project->name . " \ntanggal: " . date('d F Y', strtotime($this->project->project_date)),
                ],
            ];
    
            return [
                'line_ids' => $this->lineIds,
                'messages' => $messages,
            ];
        }
    }
}
