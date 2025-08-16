<?php

namespace Modules\Finance\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Modules\Finance\Models\ProjectDealPriceChange;

class NotifyRequestPriceChangesHasBeenApprovedNotification extends Notification
{
    use Queueable;

    protected ProjectDealPriceChange $change;

    /**
     * Create a new notification instance.
     */
    public function __construct(ProjectDealPriceChange $change)
    {
        $this->change = $change;
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
            ->subject('Price Change Request Approved')
            ->line('Your request for price changes has been approved.')
            ->line('Project Deal: ' . $this->change->projectDeal->name)
            ->line('Old Price: Rp' . number_format($this->change->old_price, 0, ',', '.'))
            ->line('New Price: Rp' . number_format($this->change->new_price, 0, ',', '.'))
            ->line('Reason: ' . $this->change->reason);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [];
    }
}
