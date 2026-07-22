<?php

use App\Services\RealtimeNotificationService;
use Illuminate\Support\Facades\Notification;
use Modules\Hrd\Jobs\DocumentDeletedNotificationJob;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Notifications\DocumentDeleted;

use function Pest\Laravel\mock;

/**
 * When a generated document is deleted the target employee is notified over two channels: the
 * realtime feed and email. The realtime service is mocked so the test asserts only the email
 * behaviour without reaching Pusher.
 */
it('emails a document-deleted notification to each target employee that has an email', function () {
    Notification::fake();
    mock(RealtimeNotificationService::class)->shouldReceive('send');

    $withEmail = Employee::factory()->create();
    $withoutEmail = Employee::factory()->create(['email' => '']);

    (new DocumentDeletedNotificationJob([$withEmail->id, $withoutEmail->id]))->handle();

    Notification::assertSentTo($withEmail, DocumentDeleted::class);
    Notification::assertNotSentTo($withoutEmail, DocumentDeleted::class);
});
