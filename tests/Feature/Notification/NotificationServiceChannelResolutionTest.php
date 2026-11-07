<?php

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Modules\Company\Models\NotificationSetting;

/**
 * NotificationService::send() resolves delivery channels in this order:
 *   1. the explicit $channels argument (per-call override), if non-empty;
 *   2. otherwise the template's notification_channel column;
 *   3. otherwise ["database"].
 *
 * The returned per-recipient result keys its 'channels' map by the channels it actually attempted,
 * so the resolution is asserted from array_keys(...). Notification::fake() / Http::fake() stop any
 * real delivery (email/slack/database go through $user->notify(); telegram uses Http).
 */
beforeEach(function () {
    Notification::fake();
    Http::fake();
});

function nscTemplate(string $action, ?array $channels): NotificationSetting
{
    $attributes = [
        'action' => $action,
        'template' => 'Hi <parameter1>',
        'template_html' => '<p>Hi &lt;parameter1&gt;</p>',
    ];

    if ($channels !== null) {
        $attributes['notification_channel'] = $channels;
    }

    return NotificationSetting::create($attributes);
}

it('derives channels from the template when the caller omits them', function () {
    nscTemplate('deadline_added', ['database', 'email']);
    $user = User::factory()->create();

    $result = NotificationService::send($user, 'deadline_added', ['parameter1' => 'Zola']);

    expect(array_keys($result[0]['channels']))->toBe(['database', 'email'])
        ->and($result[0]['channels_source'])->toBe('template');
});

it('lets an explicit channels argument override the template', function () {
    nscTemplate('deadline_added', ['database']);
    $user = User::factory()->create();

    $result = NotificationService::send($user, 'deadline_added', ['parameter1' => 'Zola'], ['email']);

    // Override wins: only email, and no "from template" marker.
    expect(array_keys($result[0]['channels']))->toBe(['email'])
        ->and($result[0])->not->toHaveKey('channels_source');
});

it('falls back to database when the template has no channel and none is passed', function () {
    nscTemplate('deadline_added', null);
    $user = User::factory()->create();

    $result = NotificationService::send($user, 'deadline_added', ['parameter1' => 'Zola']);

    expect(array_keys($result[0]['channels']))->toBe(['database'])
        ->and($result[0]['channels_source'])->toBe('template');
});
