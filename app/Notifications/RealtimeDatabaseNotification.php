<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Generic database notification used by the realtime bell notification flow.
 *
 * The payload is stored verbatim in `notifications.data` and is also the exact
 * shape pushed to the frontend through Pusher, so the live-pushed item and the
 * persisted row are identical. Topic ("general" | "division") and division id
 * live inside the payload and drive which bell tab renders the notification.
 */
class RealtimeDatabaseNotification extends Notification
{
    use Queueable;

    /**
     * @param  array{title?: string, message?: string, icon?: string, url?: ?string, action?: string, data?: array<string, mixed>}  $payload
     */
    public function __construct(
        private string $topic,
        private array $payload,
        private ?int $divisionId = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'action' => $this->payload['action'] ?? null,
            'title' => $this->payload['title'] ?? '',
            'message' => $this->payload['message'] ?? '',
            'icon' => $this->payload['icon'] ?? '🔔',
            'url' => $this->payload['url'] ?? null,
            'topic' => $this->topic,
            'division_id' => $this->divisionId,
            'data' => $this->payload['data'] ?? [],
            'read' => false,
            'created_at' => now()->toDateTimeString(),
        ];
    }
}
