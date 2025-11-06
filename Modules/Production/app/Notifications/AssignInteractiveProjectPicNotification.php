<?php

namespace Modules\Production\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Production\Models\InteractiveProject;

class AssignInteractiveProjectPicNotification extends Notification
{
    use Queueable;

    private Collection|InteractiveProject $project;

    private Collection|\Modules\Hrd\Models\Employee $employee;

    /**
     * Create a new notification instance.
     */
    public function __construct(Collection|InteractiveProject $project, Collection|\Modules\Hrd\Models\Employee $employee)
    {
        $this->project = $project;
        $this->employee = $employee;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        $output = [];

        // if ($this->employee->telegram_chat_id) {
        //     $output[] = new \App\Channels\TelegramChannel;
        // }

        return $output;
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

    public function toTelegram($notifiable)
    {
        $projectName = $this->project->name;

        $message = "You have been assigned as PIC for the Interactive Project:\n";
        $message .= '*Project Name:* '.$projectName."\n";
        $message .= '*Project Date:* '.$this->project->project_date."\n";
        $message .= '*Assigned To:* '.$this->employee->name."\n";

        return [
            'chatIds' => [$this->employee->telegram_chat_id],
            'message' => $message,
        ];
    }
}
