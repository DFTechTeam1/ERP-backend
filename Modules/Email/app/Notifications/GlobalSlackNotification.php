<?php

namespace Modules\Email\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackMessage;
use Modules\Email\Data\Notification\SendSlackMessageData;

class GlobalSlackNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public SendSlackMessageData $payload
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['slack'];
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
        return [];
    }

    public function toSlack($notifiable): SlackMessage
    {
        $slackMessage = (new SlackMessage)
            ->text($this->payload->messageTitle)
            ->headerBlock($this->payload->title);

        if ($this->payload->sectionBlock !== null) {
            foreach ($this->payload->sectionBlock as $block) {
                $slackMessage->sectionBlock(function ($section) use ($block) {
                    $section->text($block->message);
                });
            }
        }

        if ($this->payload->contextBlock !== null) {
            foreach ($this->payload->contextBlock as $contextMessage) {
                $slackMessage->contextBlock(function ($section) use ($contextMessage) {
                    $section->text($contextMessage->message);
                });
            }
        }

        return $slackMessage;
    }
}
