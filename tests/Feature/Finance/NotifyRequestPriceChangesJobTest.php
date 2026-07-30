<?php

use App\Services\RealtimeNotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Modules\Finance\Jobs\NotifyRequestPriceChangesJob;
use Modules\Finance\Models\PriceChangeReason;
use Modules\Finance\Models\ProjectDealPriceChange;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\ProjectDeal;

use function Pest\Laravel\mock;

/**
 * The bell notification tells the approvers who asked for the change, on which deal, and what the
 * price moves from and to, so a director can triage it without opening the list. The realtime
 * service is mocked so the payload can be inspected without reaching Pusher, and the mail-side
 * notification is faked because this job also sends signed approve/reject links by email.
 */
function configureApprovers(array $uids): void
{
    Cache::forever('setting', [
        ['key' => 'person_to_approve_invoice_changes', 'value' => json_encode($uids)],
    ]);
}

beforeEach(function () {
    Notification::fake();

    $this->director = Employee::factory()->withUser()->create()->refresh();

    $this->requester = Employee::factory()->withUser()->create(['name' => 'Josiah'])->refresh();

    $this->projectDeal = ProjectDeal::factory()->create(['name' => 'Wedding Gala']);

    $this->change = ProjectDealPriceChange::factory()->create([
        'project_deal_id' => $this->projectDeal->id,
        'requested_by' => $this->requester->user_id,
        'reason_id' => PriceChangeReason::factory()->create(['name' => 'Client request'])->id,
        'custom_reason' => null,
        'old_price' => 1000000,
        'new_price' => 1500000,
    ]);

    configureApprovers([$this->director->uid]);

    $this->job = new NotifyRequestPriceChangesJob(
        projectDealChangeId: $this->change->id,
        newPrice: 1500000,
        reason: 'Client request',
    );
});

function captureDirectorNotification(NotifyRequestPriceChangesJob $job): array
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

it('sends the price change request to the configured approver on the general topic', function () {
    $captured = captureDirectorNotification($this->job);

    expect($captured['recipients']->id)->toBe($this->director->id)
        ->and($captured['topic'])->toBe(RealtimeNotificationService::TOPIC_GENERAL);
});

it('names the requester, the deal and both prices in the message', function () {
    $payload = captureDirectorNotification($this->job)['payload'];

    expect($payload['title'])->toBe(__('notification.requestPriceChangesInAppTitle'))
        ->and($payload['message'])->toBe(__('notification.requestPriceChangesInAppMessage', [
            'name' => 'Josiah',
            'deal' => 'Wedding Gala',
            'oldPrice' => 'Rp. 1,000,000.00',
            'newPrice' => 'Rp. 1,500,000.00',
        ]))
        ->and($payload['message'])->toContain('Josiah')
        ->and($payload['message'])->toContain('Wedding Gala');
});

it('carries the action, the deep link and the identifiers the bell needs to act on', function () {
    $payload = captureDirectorNotification($this->job)['payload'];

    expect($payload['action'])->toBe('request_project_deal_price_changes')
        ->and($payload['url'])->toBe('/admin/deals/price-changes')
        ->and($payload['data']['price_change_id'])->toBe($this->change->id)
        ->and($payload['data']['project_deal_id'])->toBe($this->projectDeal->id)
        ->and($payload['data']['reason'])->toBe('Client request');
});

it('encrypts the price change uid so it matches the approve and reject routes', function () {
    $payload = captureDirectorNotification($this->job)['payload'];

    $decrypted = Crypt::decryptString($payload['data']['price_change_uid']);

    expect((int) $decrypted)->toBe($this->change->id);
});

it('notifies every configured approver', function () {
    $secondDirector = Employee::factory()->withUser()->create()->refresh();

    configureApprovers([$this->director->uid, $secondDirector->uid]);

    mock(RealtimeNotificationService::class)
        ->shouldReceive('send')
        ->twice();

    $this->job->handle();
});

it('sends nothing when no approver is configured', function () {
    configureApprovers([]);

    mock(RealtimeNotificationService::class)->shouldNotReceive('send');

    $this->job->handle();
});
