<?php

namespace Modules\Finance\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\ProjectDeal;

class NotifyRequestPriceChangesNotification extends Notification
{
    use Queueable;

    protected Employee $director;
    protected ProjectDeal $project;
    protected string $approvalUrl;
    protected string $rejectionUrl;

    /**
     * Create a new notification instance.
     */
    public function __construct(Employee $director, ProjectDeal $project, string $approvalUrl, string $rejectionUrl)
    {
        $this->director = $director;
        $this->project = $project;
        $this->approvalUrl = $approvalUrl;
        $this->rejectionUrl = $rejectionUrl;
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
        // make standard email content to notify the director if this project price changes needs approval
        // do not render language, 
        // just natural language text
        return (new MailMessage)
            ->subject('Project Price Change Request')
            ->markdown('mail.deals.requestPriceChange', [
                'director' => $this->director,
                'project' => $this->project,
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
