<?php

use App\Models\User;
use App\Services\PusherNotification;
use App\Services\RealtimeNotificationService;

use function Pest\Laravel\mock;

it('stores a general notification and pushes it to the user channel', function () {
    $user = User::factory()->create();

    $pusher = mock(PusherNotification::class);
    $pusher->shouldReceive('send')
        ->once()
        ->withArgs(function (string $channel, string $event, array $payload) use ($user) {
            return $channel === "private-notifications.{$user->id}"
                && $event === RealtimeNotificationService::EVENT
                && $payload['topic'] === RealtimeNotificationService::TOPIC_GENERAL
                && $payload['title'] === 'Welcome'
                && isset($payload['id']);
        });

    app(RealtimeNotificationService::class)->send(
        recipients: $user,
        topic: RealtimeNotificationService::TOPIC_GENERAL,
        payload: ['title' => 'Welcome', 'message' => 'Hello there'],
    );

    $notification = $user->notifications()->first();

    expect($notification)->not->toBeNull()
        ->and($notification->data['topic'])->toBe(RealtimeNotificationService::TOPIC_GENERAL)
        ->and($notification->data['division_id'])->toBeNull()
        ->and($notification->id)->toBe($user->notifications()->first()->id);
});

it('stores a division notification tagged with the division id', function () {
    $user = User::factory()->create();

    mock(PusherNotification::class)->shouldReceive('send')->once();

    app(RealtimeNotificationService::class)->send(
        recipients: $user,
        topic: RealtimeNotificationService::TOPIC_DIVISION,
        payload: ['title' => 'New task', 'message' => 'Assigned in Compositing'],
        divisionId: 7,
    );

    $notification = $user->notifications()->first();

    expect($notification->data['topic'])->toBe(RealtimeNotificationService::TOPIC_DIVISION)
        ->and($notification->data['division_id'])->toBe(7);
});

it('rejects a division notification without a division id', function () {
    $user = User::factory()->create();

    app(RealtimeNotificationService::class)->send(
        recipients: $user,
        topic: RealtimeNotificationService::TOPIC_DIVISION,
        payload: ['title' => 'New task'],
    );
})->throws(InvalidArgumentException::class);

it('rejects an unknown topic', function () {
    $user = User::factory()->create();

    app(RealtimeNotificationService::class)->send(
        recipients: $user,
        topic: 'marketing',
        payload: ['title' => 'Nope'],
    );
})->throws(InvalidArgumentException::class);
