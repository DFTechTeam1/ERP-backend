<?php

namespace Modules\Production\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class NotifyApprovalRecipientWhenInteractiveHasBeenDeleteNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private readonly string $name,
        private readonly string $date,
        private readonly string $recipientName,
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
        // load email configuration
        setEmailConfiguration();

        $formattedDate = date('d M Y', strtotime($this->date));
        return (new MailMessage)
            ->subject('Interactive Event has been deleted')
            ->line("Dear {$this->recipientName},")
            ->line("The interactive event '{$this->name}' scheduled on {$formattedDate} has been deleted.")
            ->line('Thank you for your attention.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [];
    }
}
