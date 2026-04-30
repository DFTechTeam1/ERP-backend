<?php

namespace Modules\Email\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackMessage;

class ResignationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public bool $success,
        public string $employeeName,
        public string $employeeEmail,
        public ?string $errorMessage = null,
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(mixed $notifiable): array
    {
        return ['slack'];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(mixed $notifiable): array
    {
        return [];
    }

    public function toSlack(mixed $notifiable): SlackMessage
    {
        $status = $this->success ? ':white_check_mark: Success' : ':x: Failed';
        $title = $this->success ? 'Employee Resignation Processed' : 'Employee Resignation Failed';

        $message = $this->success
            ? "The resignation process for *{$this->employeeName}* has been completed successfully."
            : "The resignation process for *{$this->employeeName}* failed.\n*Reason:* {$this->errorMessage}";

        $slackMessage = (new SlackMessage)
            ->text("{$status} — HR Resign Action")
            ->headerBlock($title)
            ->sectionBlock(function ($block) use ($message) {
                $block->text($message);
            });

        if ($this->success) {
            $slackMessage->sectionBlock(function ($block) {
                $block->text(
                    ":warning: *Action Required for Developer:*\n".
                    "• Delete the employee's office email account ({$this->employeeEmail}) from the email provider.\n".
                    '• Check and revoke all related accounts (e.g. ERP Account, Official Email, or any other integrated services).'
                );
            });
        }

        return $slackMessage->contextBlock(function ($block) {
            $block->text('Triggered at: '.now()->toDateTimeString());
        });
    }
}
