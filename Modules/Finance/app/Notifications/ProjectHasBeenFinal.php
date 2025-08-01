<?php

namespace Modules\Finance\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Modules\Production\Models\ProjectDeal;

class ProjectHasBeenFinal extends Notification
{
    use Queueable;

    private $projectDeal;

    /**
     * Create a new notification instance.
     */
    public function __construct(ProjectDeal $projectDeal)
    {
        $this->projectDeal = $projectDeal;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return [
            'mail',
            'database'
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        setEmailConfiguration();
        
        return (new MailMessage)
            ->subject('Project Finalized - Ready for invoicing')
            ->greeting('Hello Finance Team,')
            ->line("The project **{$this->projectDeal->name}** has been marked as **Final**")
            ->line('You may now begin preparing and issuing the invoice to the customer')
            ->line('Please ensure that all relevant billing details are accurate before proceeding.')
            ->line('Thank you for you prompt attention.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'finalized',
            'title' => 'Project finalized - Invoice can be issued',
            'message' => "The project <b>{$this->projectDeal->name}</b> has been finalized. You can now create an invoice for the customer",
            'button' => null,
            'href' => '/admin/deals/' . \Illuminate\Support\Facades\Crypt::encryptString($this->projectDeal->id)
        ];
    }
}
