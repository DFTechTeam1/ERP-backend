<?php

use App\Enums\Production\ProjectDealChangeStatus;
use App\Services\RealtimeNotificationService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Modules\Hrd\Models\Employee;
use Modules\Production\Jobs\NotifyApprovalProjectDealChangeJob;
use Modules\Production\Models\ProjectDealChange;
use Modules\Production\Notifications\NotifyApprovalProjectDealChangeNotification;

use function Pest\Laravel\mock;

/**
 * The job closes the loop back to the person who asked for the change: a mail with the outcome and
 * a bell notification naming whoever decided it. The approver is read from a different relation per
 * outcome (approval vs rejecter), so both paths are covered - and neither a change that no longer
 * exists nor one without a requester may blow up the queue worker.
 */
beforeEach(function () {
    Notification::fake();

    $this->requester = Employee::factory()->withUser()->create(['name' => 'Sharon'])->refresh();
    $this->approver = Employee::factory()->withUser()->create(['name' => 'Yanuar'])->refresh();
});

function makeDecidedChange(Employee $requester, Employee $decider, string $type): ProjectDealChange
{
    $decision = $type === 'approved'
        ? [
            'approval_by' => $decider->user_id,
            'approval_at' => now(),
            'status' => ProjectDealChangeStatus::Approved->value,
        ]
        : [
            'rejected_by' => $decider->user_id,
            'rejected_at' => now(),
            'status' => ProjectDealChangeStatus::Rejected->value,
        ];

    return ProjectDealChange::factory()->create(array_merge(
        ['requested_by' => $requester->user_id],
        $decision
    ));
}

function captureDecisionNotification(NotifyApprovalProjectDealChangeJob $job): array
{
    $captured = [];

    mock(RealtimeNotificationService::class)
        ->shouldReceive('send')
        ->once()
        ->andReturnUsing(function ($recipients, $topic, $payload) use (&$captured) {
            $captured = compact('recipients', 'topic', 'payload');
        });

    $job->handle();

    return $captured;
}

it('mails the requester naming the approver when a change is approved', function () {
    $change = makeDecidedChange($this->requester, $this->approver, 'approved');

    (new NotifyApprovalProjectDealChangeJob(changeId: $change->id, type: 'approved'))->handle();

    Notification::assertSentTo(
        $this->requester,
        NotifyApprovalProjectDealChangeNotification::class
    );
});

it('mails the requester naming the rejecter when a change is rejected', function () {
    $change = makeDecidedChange($this->requester, $this->approver, 'rejected');

    (new NotifyApprovalProjectDealChangeJob(changeId: $change->id, type: 'rejected'))->handle();

    Notification::assertSentTo(
        $this->requester,
        NotifyApprovalProjectDealChangeNotification::class
    );
});

it('sends the bell notification to the requester on the general topic', function () {
    $change = makeDecidedChange($this->requester, $this->approver, 'approved');

    $captured = captureDecisionNotification(
        new NotifyApprovalProjectDealChangeJob(changeId: $change->id, type: 'approved')
    );

    expect($captured['recipients']->id)->toBe($this->requester->id)
        ->and($captured['topic'])->toBe(RealtimeNotificationService::TOPIC_GENERAL);
});

it('names the approver and the deal in the approved message', function () {
    $change = makeDecidedChange($this->requester, $this->approver, 'approved');

    $payload = captureDecisionNotification(
        new NotifyApprovalProjectDealChangeJob(changeId: $change->id, type: 'approved')
    )['payload'];

    expect($payload['title'])->toBe(__('notification.dealChangesApprovedTitle'))
        ->and($payload['message'])->toBe(__('notification.dealChangesApprovedMessage', [
            'name' => 'Yanuar',
            'deal' => $change->projectDeal->name,
        ]))
        ->and($payload['icon'])->toBe('✅')
        ->and($payload['action'])->toBe('project_deal_change_approved');
});

it('names the rejecter and the deal in the rejected message', function () {
    $change = makeDecidedChange($this->requester, $this->approver, 'rejected');

    $payload = captureDecisionNotification(
        new NotifyApprovalProjectDealChangeJob(changeId: $change->id, type: 'rejected')
    )['payload'];

    expect($payload['title'])->toBe(__('notification.dealChangesRejectedTitle'))
        ->and($payload['message'])->toBe(__('notification.dealChangesRejectedMessage', [
            'name' => 'Yanuar',
            'deal' => $change->projectDeal->name,
        ]))
        ->and($payload['icon'])->toBe('❌')
        ->and($payload['action'])->toBe('project_deal_change_rejected');
});

it('carries the deep link and the identifiers the bell needs', function () {
    $change = makeDecidedChange($this->requester, $this->approver, 'approved');

    $payload = captureDecisionNotification(
        new NotifyApprovalProjectDealChangeJob(changeId: $change->id, type: 'approved')
    )['payload'];

    expect($payload['url'])->toBe('/admin/deals/changes')
        ->and($payload['data']['deal_change_id'])->toBe($change->id)
        ->and($payload['data']['project_deal_id'])->toBe($change->project_deal_id)
        ->and($payload['data']['type'])->toBe('approved')
        ->and($payload['data']['decided_by'])->toBe('Yanuar')
        ->and((int) Crypt::decryptString($payload['data']['deal_change_uid']))->toBe($change->id);
});

it('falls back to a placeholder when the decider can no longer be resolved', function () {
    $change = ProjectDealChange::factory()->create([
        'requested_by' => $this->requester->user_id,
        'approval_by' => null,
        'status' => ProjectDealChangeStatus::Approved->value,
    ]);

    $payload = captureDecisionNotification(
        new NotifyApprovalProjectDealChangeJob(changeId: $change->id, type: 'approved')
    )['payload'];

    expect($payload['data']['decided_by'])->toBe('-');
});

it('does nothing when the change no longer exists', function () {
    mock(RealtimeNotificationService::class)->shouldNotReceive('send');

    (new NotifyApprovalProjectDealChangeJob(changeId: 0, type: 'approved'))->handle();

    Notification::assertNothingSent();
});

it('does nothing when the change has no requester to notify', function () {
    $change = ProjectDealChange::factory()->create([
        'requested_by' => null,
        'approval_by' => $this->approver->user_id,
        'status' => ProjectDealChangeStatus::Approved->value,
    ]);

    mock(RealtimeNotificationService::class)->shouldNotReceive('send');

    (new NotifyApprovalProjectDealChangeJob(changeId: $change->id, type: 'approved'))->handle();

    Notification::assertNothingSent();
});
