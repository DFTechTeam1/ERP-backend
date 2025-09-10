<?php

namespace Modules\Production\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NotifyApprovalProjectDealChangeNotification extends Notification
{
    use Queueable;

    private $dealChange;

    private $type;

    private $actor;

    /**
     * Create a new notification instance.
     */
    public function __construct(object $dealChange, string $type, string $actor)
    {
        $this->dealChange = $dealChange;
        $this->type = $type;
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

        return (new MailMessage)
            ->greeting("Dear {$this->dealChange->requester->employee->name}")
            ->line(
                "Your changes in event {$this->dealChange->projectDeal->name} has been {$this->type} by {$this->actor}"
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
