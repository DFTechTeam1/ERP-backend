<?php

namespace Modules\Company\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ContextBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;

class NasCreationSlackNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly array $logData,
    )
    {
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
        $eventName = $this->logData['payload']['project_name'];
        $errorMessage = $this->logData['error_message'] ?? 'N/A';
        $endpointUrl = $this->logData['endpoint'] ?? 'N/A';
        return (new SlackMessage)
            ->text('NAS folder queue creation process completed!')
            ->headerBlock($this->logData['status'] == 'SUCCESS' ? '✅ NAS Folder Queue Created' : '❌ NAS Folder Queue Failed')
            ->contextBlock(function (ContextBlock $block) use($endpointUrl) {
                $block->text("Process executed at *{$this->logData['timestamp']}*\nURL: *{$endpointUrl}*")->markdown();
            })
            ->sectionBlock(function (SectionBlock $block) use ($eventName) {
                $block->text("*Event Details*\nEvent Name: *{$eventName}*\nEvent Date: *{$this->logData['timestamp']}*")->markdown();
            })
            ->dividerBlock()
            ->sectionBlock(function (SectionBlock $block) use ($eventName, $errorMessage) {
                if ($this->logData['status'] == 'SUCCESS') {
                    $block->text("*Status:* ✅ Successfully Created\n*Folder Path:* `{$eventName}`")->markdown();
                } else {
                    $block->text("*Status:* ❌ Failed\n*Error:* {$errorMessage}")->markdown();
                }
            })
            ->contextBlock(function (ContextBlock $block) {
                $block->text($this->logData['status'] == 'SUCCESS' 
                    ? "NAS folder is ready for file uploads" 
                    : "Please check the logs or contact system administrator"
                )->markdown();
            });
    }
}
