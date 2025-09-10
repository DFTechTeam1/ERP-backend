<?php

namespace Modules\Production\Notifications;

use App\Services\GeneralService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Production\Models\ProjectDeal;

class PaymentDueReminderNotification extends Notification
{
    use Queueable;

    public $projectDeal;

    public $invoiceNumber;

    /**
     * Create a new notification instance.
     */
    public function __construct(ProjectDeal $projectDeal)
    {
        $this->projectDeal = $projectDeal;

        // general service
        $generalService = new GeneralService;
        $this->invoiceNumber = $generalService->generateInvoiceNumber();
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

        $uid = \Illuminate\Support\Facades\Crypt::encryptString($this->projectDeal->id);

        return (new MailMessage)
            ->subject('Payment Due')
            ->markdown('mail.payment.paymentDue', [
                'projectDeal' => $this->projectDeal,
                'invoiceNumber' => $this->invoiceNumber,
                'url' => url("deal-invoice/download/{$uid}/stream").'?amount='.$this->projectDeal->getRemainingPayment().'&date='.now()->format('Y-m-d'),
                'remainingPayment' => $this->projectDeal->getRemainingPayment(formatPrice: true),
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
