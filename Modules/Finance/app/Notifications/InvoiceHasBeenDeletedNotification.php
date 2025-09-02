<?php

namespace Modules\Finance\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceHasBeenDeletedNotification extends Notification
{
    use Queueable;

    private $financeUser;

    private $actor;

    private $parentNumber;

    private $projectName;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $financeUser, User $actor, string $parentNumber, string $projectName)
    {
        $this->financeUser = $financeUser;
        $this->actor = $actor;
        $this->parentNumber = $parentNumber;
        $this->projectName = $projectName;
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
            ->subject('Invoice has been deleted')
            ->greeting('Dear '.$this->financeUser->employee->name)
            ->line("Invoice **{$this->parentNumber}** for event {$this->projectName} has been deleted by {$this->actor->employee->name}");
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [];
    }
}
