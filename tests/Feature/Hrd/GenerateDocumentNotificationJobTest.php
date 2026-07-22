<?php

use App\Services\RealtimeNotificationService;
use Illuminate\Support\Facades\Notification;
use Modules\Hrd\Jobs\GenerateDocumentNotificationJob;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Notifications\DocumentReadyToSign;

use function Pest\Laravel\mock;

/**
 * When a signable document is generated the notification job fans out to each signer over two
 * channels: the realtime feed and email. The realtime service is mocked here so the test asserts
 * only the newly added email behaviour without reaching Pusher.
 */
it('emails a document-ready notification to each signer that has an email', function () {
    Notification::fake();
    mock(RealtimeNotificationService::class)->shouldReceive('send');

    $withEmail = Employee::factory()->create();
    $withoutEmail = Employee::factory()->create(['email' => '']);

    (new GenerateDocumentNotificationJob([$withEmail->id, $withoutEmail->id]))->handle();

    Notification::assertSentTo($withEmail, DocumentReadyToSign::class);
    Notification::assertNotSentTo($withoutEmail, DocumentReadyToSign::class);
});
