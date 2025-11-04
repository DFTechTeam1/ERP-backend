<?php

namespace Modules\Finance\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ContextBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;
use Modules\Production\Models\ProjectDeal;

class InvoiceHasBeenCreated extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly ProjectDeal $projectDeal,
        public readonly ?string $eventName = null,
        public readonly ?string $invoiceNumber = null,
        public readonly ?string $totalAmount = null,
        public readonly ?string $dueDate = null,
        public readonly ?string $issuedAt = null,
        public readonly ?string $actorName = null,
        public readonly ?string $paymentStatus = null,
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        $output = [
            'database',
        ];

        if ($this->invoiceNumber && $this->totalAmount && $this->eventName) {
            $output[] = 'slack';
        }

        return $output;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('The introduction to the notification.')
            ->action('Notification Action', 'https://laravel.com')
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'finalized',
            'title' => 'Invoice issued to customer',
            'message' => "An invoice has been created for project <b>{$this->projectDeal->name}</b>. Ensure it’s tracked and sent to the customer.",
            'button' => null,
            'href' => '/admin/deals/'.\Illuminate\Support\Facades\Crypt::encryptString($this->projectDeal->id),
        ];
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        return (new SlackMessage)
            ->text("Invoice has been issued for {$this->eventName}")
            ->headerBlock('🧾 Invoice Issued')
            ->contextBlock(function (ContextBlock $block) {
                $block->text("Invoice issued at *{$this->issuedAt}* by *{$this->actorName}*")->markdown();
            })
            ->sectionBlock(function (SectionBlock $block) {
                $block->text("*Invoice Details*\nEvent Name: *{$this->eventName}*\nInvoice Number: *{$this->invoiceNumber}*\nTotal Amount: *{$this->totalAmount}*\nDue Date: *{$this->dueDate}*")->markdown();
            })
            ->dividerBlock()
            ->sectionBlock(function (SectionBlock $block) {
                $block->text("Status: *{$this->paymentStatus}*")->markdown();
            });
    }
}