<?php

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Modules\Company\Models\NotificationSetting;

/**
 * A template may define only a plain body, only an HTML body, or an empty one. processTemplate()
 * used to require a non-null string, so a null template_html threw a TypeError in send() (rendered
 * before the channel loop, so it broke every send regardless of channel). These assert that an
 * empty/null body renders as an empty string instead of crashing.
 */
beforeEach(function () {
    Notification::fake();
    Http::fake();
});

it('sends without error when the template has no HTML body (template_html null)', function () {
    NotificationSetting::create([
        'action' => 'plain_only',
        'template' => 'Hi <parameter1>',
        'template_html' => null,
        'notification_channel' => ['database'],
    ]);
    $user = User::factory()->create();

    $result = NotificationService::send($user, 'plain_only', ['parameter1' => 'Zola']);

    expect($result[0]['channels']['database']['success'])->toBeTrue();
});

it('renders empty when both the plain and HTML bodies are empty', function () {
    expect(NotificationService::preview('', null, []))
        ->toBe(['rendered' => '', 'rendered_html' => '']);
});

it('renders the HTML body while the plain body is empty', function () {
    $out = NotificationService::preview('', '<p>Hi &lt;parameter1&gt;</p>', ['parameter1' => 'Zola']);

    expect($out['rendered'])->toBe('')
        ->and($out['rendered_html'])->toBe('<p>Hi Zola</p>');
});
