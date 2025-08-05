<?php

namespace Modules\Production\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class NotifyApprovalProjectDealChangeNotification extends Notification
{
    use Queueable;

    private $dealChange;

    private $type;

    /**
     * Create a new notification instance.
     */
    public function __construct(object $dealChange, string $type)
    {
        $this->dealChange = $dealChange;
        $this->type = $type;
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
            ->greeting("Dear {$this->dealChange->requester->employee->name}")
            ->line("Your changes in event {$this->dealChange->projectDeal->name} has been {$this->type} by {$this->dealChange->approval->employee->name}");
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [];
    }
}
