<?php

namespace Modules\Production\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Modules\Hrd\Models\Employee;

class RemindAssignmentMarcommNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly Collection $projects,
        public readonly Employee|Collection $marcommPic
    )
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        setEmailConfiguration();

        return (new MailMessage)
            ->subject('Reminder: Assignment of Marcomm Team for Upcoming Events')
            ->markdown(
                'mail.incharges.remind-assignment-marcomm',
                [
                    'projects' => $this->projects,
                    'marcommPic' => $this->marcommPic,
                    'assignmentPortal' => 'https://google.com'
                ]
            );
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [];
    }
}
