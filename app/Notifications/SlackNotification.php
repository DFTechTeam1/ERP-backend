<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\SlackMessage;

class SlackNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private string $action;
    private string $message;
    private array $data;
    private array $options;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        string $action,
        string $message,
        array $data = [],
        array $options = []
    ) {
        $this->action = $action;
        $this->message = $message;
        $this->data = $data;
        $this->options = $options;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['slack'];
    }

    /**
     * Get the Slack representation of the notification.
     */
    public function toSlack($notifiable): SlackMessage
    {
        $slack = (new SlackMessage)
            ->content($this->message)
            ->from($this->options['from'] ?? config('app.name'), $this->options['icon'] ?? ':bell:');

        // Add attachment if provided
        if (isset($this->options['attachment'])) {
            $slack->attachment(function ($attachment) {
                $attachment->title($this->options['attachment']['title'] ?? 'Details')
                    ->fields($this->options['attachment']['fields'] ?? [])
                    ->color($this->options['attachment']['color'] ?? '#007bff');
            });
        }

        return $slack;
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'action' => $this->action,
            'message' => $this->message,
            'data' => $this->data,
        ];
    }
}
