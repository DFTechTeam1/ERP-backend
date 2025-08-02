<?php

namespace Modules\Finance\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;

class InvoiceDueCheckNotification extends Notification
{
    use Queueable;

    private $invoices;

    private $user;

    /**
     * Create a new notification instance.
     */
    public function __construct(Collection $invoices, User $user)
    {
        $this->invoices = $invoices;
        $this->user = $user;
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
            ->subject('Payment Due')
            ->markdown('mail.payment.reminder', [
                'invoices' => $this->invoices,
                'user' => $this->user
            ]);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'reminder',
            'title' => '🔔 Payment Due Reminder',
            'message' => "You have {$this->invoices->count()} inovice(s) due soon.",
            'button' => null,
            'href' => '/admin/deals'
        ];
    }
}
