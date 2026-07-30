<?php

use App\Services\RealtimeNotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Modules\Hrd\Models\Employee;
use Modules\Production\Jobs\NotifyProjectDealChangesJob;
use Modules\Production\Models\ProjectDealChange;

use function Pest\Laravel\mock;

/**
 * The bell notification tells the approvers who asked, on which deal, and which fields the request
 * touches, so a director can triage without opening the list. The realtime service is mocked so the
 * payload can be inspected without reaching Pusher, and the mail-side notification is faked because
 * this job also mails signed approve/reject links.
 */
function configureDealChangeApprovers(array $uids): void
{
    Cache::forever('setting', [
        ['key' => 'person_to_approve_invoice_changes', 'value' => json_encode($uids)],
    ]);
}

beforeEach(function () {
    Notification::fake();

    $this->director = Employee::factory()->withUser()->create()->refresh();
    $this->requester = Employee::factory()->withUser()->create(['nickname' => 'Grace'])->refresh();

    $this->change = ProjectDealChange::factory()->create([
        'requested_by' => $this->requester->user_id,
        'detail_changes' => [
            ['label' => 'Name', 'old_value' => 'Gala', 'new_value' => 'Gala Dinner'],
            ['label' => 'Event Note', 'old_value' => '', 'new_value' => 'Bring extra LED'],
        ],
    ]);

    configureDealChangeApprovers([$this->director->uid]);

    $this->job = new NotifyProjectDealChangesJob(changesId: $this->change->id);
});

function captureDealChangeNotification(NotifyProjectDealChangesJob $job): array
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

it('sends the request to the configured approver on the general topic', function () {
    $captured = captureDealChangeNotification($this->job);

    expect($captured['recipients']->id)->toBe($this->director->id)
        ->and($captured['topic'])->toBe(RealtimeNotificationService::TOPIC_GENERAL);
});

it('names the requester, the deal and the fields being changed', function () {
    $payload = captureDealChangeNotification($this->job)['payload'];

    expect($payload['title'])->toBe(__('notification.requestDealChangesInAppTitle'))
        ->and($payload['message'])->toBe(__('notification.requestDealChangesInAppMessage', [
            'name' => 'Grace',
            'deal' => $this->change->projectDeal->name,
            'fields' => 'Name, Event Note',
        ]))
        ->and($payload['message'])->toContain('Grace')
        ->and($payload['message'])->toContain('Name, Event Note');
});

it('carries the action, the deep link and the identifiers the bell needs to act on', function () {
    $payload = captureDealChangeNotification($this->job)['payload'];

    expect($payload['action'])->toBe('request_project_deal_changes')
        ->and($payload['url'])->toBe('/admin/deals/changes')
        ->and($payload['data']['deal_change_id'])->toBe($this->change->id)
        ->and($payload['data']['project_deal_id'])->toBe($this->change->project_deal_id)
        ->and($payload['data']['detail_changes'])->toHaveCount(2);
});

it('encrypts the change uid so it matches the approve and reject routes', function () {
    $payload = captureDealChangeNotification($this->job)['payload'];

    expect((int) Crypt::decryptString($payload['data']['deal_change_uid']))->toBe($this->change->id);
});

it('falls back to a placeholder when the change has no requester', function () {
    $orphan = ProjectDealChange::factory()->create(['requested_by' => null]);

    $payload = captureDealChangeNotification(new NotifyProjectDealChangesJob(changesId: $orphan->id))['payload'];

    expect($payload['message'])->toContain('-');
});

it('notifies every configured approver', function () {
    $secondDirector = Employee::factory()->withUser()->create()->refresh();

    configureDealChangeApprovers([$this->director->uid, $secondDirector->uid]);

    mock(RealtimeNotificationService::class)
        ->shouldReceive('send')
        ->twice();

    $this->job->handle();
});

it('sends nothing when no approver is configured', function () {
    configureDealChangeApprovers([]);

    mock(RealtimeNotificationService::class)->shouldNotReceive('send');

    $this->job->handle();
});

it('sends nothing when the change no longer exists', function () {
    mock(RealtimeNotificationService::class)->shouldNotReceive('send');

    (new NotifyProjectDealChangesJob(changesId: 0))->handle();

    Notification::assertNothingSent();
});
