<?php

namespace Modules\Production\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class AssignVjNotification extends Notification
{
    use Queueable;

    public $lineIds;
    
    public $project;

    public $creator;

    public $employee;

    /**
     * Create a new notification instance.
     */
    public function __construct(array $lineIds, $project, $employee, $creator)
    {
        $this->lineIds = $lineIds;

        $this->project = $project;

        $this->employee = $employee;

        $this->creator = $creator;
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
        $link = "/admin/production/project/{$this->project->uid}";

        return [
            'title' => __('global.newProject'),
            'message' => __('global.assignedVjToProject', [
                'event' => $this->project->name . ' (' . date('d F Y', strtotime($this->project->project_date)) . ')',
                'creator' => $this->creator]
            ),
            'link' => $link,
        ];
    }

    public function toLine($notifiable)
    {
        $messages = [
            [
                'type' => 'text',
                'text' => 'Halo ' . $this->employee->nickname . ", " . $this->creator . " baru saja memilihmu sebagai VJ / Operator untuk event " . $this->project->name . " di tanggal " . date('d F Y', strtotime($this->project->project_date)),
            ]
        ];

        return [
            'line_ids' => $this->lineIds,
            'messages' => $messages,
        ];
    }
}
