<?php

use App\Services\RealtimeNotificationService;
use Modules\Hrd\Jobs\DocumentCompletedNotificationJob;
use Modules\Hrd\Models\Employee;

use function Pest\Laravel\mock;

it('notifies the document owner in-app when the job runs', function () {
    $employee = Employee::factory()->create();

    mock(RealtimeNotificationService::class)
        ->shouldReceive('send')
        ->once()
        ->withArgs(function ($recipients, $topic, $payload) use ($employee) {
            return $recipients instanceof Employee
                && $recipients->id === $employee->id
                && $topic === RealtimeNotificationService::TOPIC_GENERAL
                && ($payload['action'] ?? null) === 'document_completed'
                && ($payload['data']['employee_document_uid'] ?? null) === 'doc-uid-123';
        });

    (new DocumentCompletedNotificationJob($employee->id, 'doc-uid-123'))->handle();
});

it('does nothing when the document owner no longer exists', function () {
    mock(RealtimeNotificationService::class)
        ->shouldNotReceive('send');

    (new DocumentCompletedNotificationJob(999999, 'doc-uid-123'))->handle();
});
