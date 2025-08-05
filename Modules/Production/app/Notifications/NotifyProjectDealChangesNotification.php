<?php

namespace Modules\Production\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class NotifyProjectDealChangesNotification extends Notification
{
    use Queueable;

    private $changes;

    private $employee;

    /**
     * Create a new notification instance.
     */
    public function __construct(\Modules\Production\Models\ProjectDealChange $changes, object $employee)
    {
        $this->changes = $changes;
        $this->employee = $employee;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return [
            'mail'
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        setEmailConfiguration();

        return (new MailMessage)
            ->subject("Approval Required: Event Data Modification by " . $this->changes->requester->employee->nickname)
            ->markdown('mail.deals.changeRequest', [
                'data' => $this->changes,
                'director' => $this->employee,
                'approvalUrl' => '',
                'rejectionUrl' => '',
            ]);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [];
    }
}
