<?php

namespace Modules\Production\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NotifyProjectDealChangesNotification extends Notification
{
    use Queueable;

    private $changes;

    private $employee;

    private $approvalUrl;

    private $rejectionUrl;

    /**
     * Create a new notification instance.
     */
    public function __construct(\Modules\Production\Models\ProjectDealChange $changes, object $employee, string $approvalUrl, string $rejectionUrl)
    {
        $this->changes = $changes;
        $this->employee = $employee;
        $this->approvalUrl = $approvalUrl;
        $this->rejectionUrl = $rejectionUrl;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return [
            'mail',
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        setEmailConfiguration();

        return (new MailMessage)
            ->subject('Approval Required: Event Data Modification by '.$this->changes->requester->employee->nickname)
            ->markdown('mail.deals.changeRequest', [
                'data' => $this->changes,
                'director' => $this->employee,
                'approvalUrl' => $this->approvalUrl,
                'rejectionUrl' => $this->rejectionUrl,
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
