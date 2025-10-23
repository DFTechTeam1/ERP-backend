<?php

namespace Modules\Finance\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ContextBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;
use Modules\Production\Models\ProjectDeal;

class ProjectHasBeenFinal extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly ProjectDeal $projectDeal,
        public readonly ?string $publishedAt = null,
        public readonly ?string $actorName = null,
        public readonly ?string $finalPrice = null,
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        $output = [
            'mail',
            'database'
        ];

        if (!$this->publishedAt && !$this->actorName && !$this->finalPrice) {
            $output[] = 'slack';
        }

        return $output;
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

    public function toSlack(object $notifiable): SlackMessage
    {
        return (new SlackMessage)
            ->text('There is an event that has just been published!')
            ->headerBlock('Event Published')
            ->contextBlock(function (ContextBlock $block) {
                // write more proper notification about published event, i have information about publishedAt, actorName, finalPrice, event name, with interactive or not, led area total detail
                $block->text("Event published at *{$this->publishedAt}* by *{$this->actorName}*")->markdown();
            })
            ->sectionBlock(function (SectionBlock $block) {
                $block->text("Event published with dealing price *{$this->finalPrice}*\nEvent name: *Sample Event*\nWith Interactive: *Yes*\nLED area total: 24<sup>2</sup>")->markdown();
            });
    }
}
