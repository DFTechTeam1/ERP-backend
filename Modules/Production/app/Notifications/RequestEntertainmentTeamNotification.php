<?php

namespace Modules\Production\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class RequestEntertainmentTeamNotification extends Notification
{
    use Queueable;

    private $lineIds;

    private $message;

    private $project;

    /**
     * Create a new notification instance.
     */
    public function __construct(array $lineIds, array $message, object $project)
    {
        $this->lineIds = $lineIds;

        $this->message = $message;

        $this->project = $project;
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
        $link = '/admin/production/team-transfer';

        return [
            'title' => __('global.newRequestTeam'),
            'message' => __('global.newRequestTeamText', ['event' => $this->project->name]),
            'link' => $link,
        ];
    }

    public function toLine($notifiable)
    {   
        return [
            'line_ids' => $this->lineIds,
            'messages' => $this->message,
        ];

    }
}