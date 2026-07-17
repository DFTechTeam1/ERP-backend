<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\RealtimeDatabaseNotification;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Hrd\Models\Employee;

/**
 * Core "store + deliver" for realtime bell notifications.
 *
 * Persists a Laravel database notification and pushes the same payload over
 * Pusher so the frontend bell updates live. This only ever touches the
 * `database` channel; email/Slack/WhatsApp/Line/Telegram are untouched.
 *
 * Call it explicitly from any place that needs a realtime notification:
 *
 *     app(RealtimeNotificationService::class)->send(
 *         recipients: $user,               // User|Employee, or a collection of them
 *         topic: RealtimeNotificationService::TOPIC_DIVISION,
 *         payload: [
 *             'title'   => 'New task assigned',
 *             'message' => 'You have a new task in Compositing',
 *             'icon'    => '📋',
 *             'url'     => '/admin/production/board',
 *             'action'  => 'user_has_been_assigned_to_task',
 *             'data'    => [...],
 *         ],
 *         divisionId: $divisionId,          // required when topic = division
 *     );
 */
class RealtimeNotificationService
{
    public const TOPIC_GENERAL = 'general';

    public const TOPIC_DIVISION = 'division';

    public const EVENT = 'new-db-notification';

    public function __construct(private PusherNotification $pusher) {}

    /**
     * Store a database notification for each recipient and push it live.
     *
     * @param  Model|iterable<int, Model>  $recipients  User and/or Employee models
     * @param  array{title?: string, message?: string, icon?: string, url?: ?string, action?: string, data?: array<string, mixed>}  $payload
     */
    public function send(
        Model|iterable $recipients,
        string $topic,
        array $payload,
        ?int $divisionId = null,
    ): void {
        if (! in_array($topic, [self::TOPIC_GENERAL, self::TOPIC_DIVISION], true)) {
            throw new InvalidArgumentException("Unknown notification topic [{$topic}].");
        }

        if ($topic === self::TOPIC_DIVISION && $divisionId === null) {
            throw new InvalidArgumentException('A division notification requires a divisionId.');
        }

        foreach ($this->normalizeRecipients($recipients) as $recipient) {
            $this->sendToRecipient($recipient, $topic, $payload, $divisionId);
        }
    }

    private function sendToRecipient(
        Model $recipient,
        string $topic,
        array $payload,
        ?int $divisionId,
    ): void {
        $notification = new RealtimeDatabaseNotification($topic, $payload, $divisionId);
        $notification->id = (string) Str::uuid();

        // notifyNow (not queued) so the row is persisted before we push, and the
        // pushed item carries the same id the database row was written with.
        $recipient->notifyNow($notification);

        $userId = $this->resolveUserId($recipient);

        if ($userId === null) {
            return;
        }

        $this->pusher->send(
            channel: "private-notifications.{$userId}",
            event: self::EVENT,
            payload: array_merge(
                $notification->toArray($recipient),
                [
                    'id' => $notification->id,
                    // match the display format the read endpoint returns
                    'created_at' => now()->format('d F Y H:i'),
                ],
            ),
        );
    }

    /**
     * @param  Model|iterable<int, Model>  $recipients
     * @return iterable<int, Model>
     */
    private function normalizeRecipients(Model|iterable $recipients): iterable
    {
        if ($recipients instanceof Model) {
            return [$recipients];
        }

        if ($recipients instanceof Collection || $recipients instanceof EloquentCollection) {
            return $recipients->all();
        }

        return $recipients;
    }

    /**
     * Resolve the user id that owns the Pusher channel for this recipient.
     */
    private function resolveUserId(Model $recipient): ?int
    {
        if ($recipient instanceof User) {
            return $recipient->id;
        }

        if ($recipient instanceof Employee) {
            return $recipient->user_id;
        }

        return null;
    }
}
