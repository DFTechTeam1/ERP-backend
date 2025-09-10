<?php

namespace Modules\Finance\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\ProjectDeal;

class NotifyRequestPriceChangesNotification extends Notification
{
    use Queueable;

    protected Employee $director;
    protected Employee|Collection $actor;
    protected ProjectDeal $project;
    protected string $approvalUrl;
    protected string $rejectionUrl;
    protected string $reason;
    protected string $oldPrice;
    protected string $newPrice;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        Employee $director,
        Employee|Collection $actor,
        ProjectDeal $project,
        string $approvalUrl,
        string $rejectionUrl,
        string $reason,
        string $oldPrice,
        string $newPrice,
    )
    {
        $this->director = $director;
        $this->project = $project;
        $this->approvalUrl = $approvalUrl;
        $this->rejectionUrl = $rejectionUrl;
        $this->reason = $reason;
        $this->oldPrice = $oldPrice;
        $this->newPrice = $newPrice;
        $this->actor = $actor;
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
                'reason' => $this->reason,
                'oldPrice' => $this->oldPrice,
                'newPrice' => $this->newPrice,
                'user' => $this->actor,
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
