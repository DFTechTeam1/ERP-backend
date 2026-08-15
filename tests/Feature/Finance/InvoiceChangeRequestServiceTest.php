<?php

use App\Enums\Finance\InvoiceRequestUpdateStatus;
use App\Enums\System\BaseRole;
use App\Models\User;
use App\Services\PusherNotification;
use App\Services\RealtimeNotificationService;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\InvoiceRequestUpdate;
use Modules\Finance\Services\InvoiceChangeRequestService;
use Modules\Finance\Services\InvoiceService;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\ProjectDeal;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;

/**
 * Coverage for the Request → Invoice Changes menu backend.
 *
 * Rules under test:
 *   - List filters by status tab and returns statusCount for badges.
 *   - Approve delegates to InvoiceService and fires a realtime bell to
 *     the original requester.
 *   - Reject delegates to InvoiceService with reason and fires a bell.
 *   - Already-actioned rows cannot be re-actioned (422).
 *   - Missing id returns 404.
 */

function ensureInvChgRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name, 'guard_name' => 'sanctum']);
}

function actAsInvChgRole(string $roleName): User
{
    ensureInvChgRole($roleName);
    $employee = Employee::factory()->create();
    $user = User::factory()->create(['employee_id' => $employee->id]);
    $user->assignRole($roleName);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    actingAs($user);

    return $user;
}

beforeEach(function () {
    // The realtime service pushes to Pusher inside sendToRecipient. In tests
    // there is no Pusher connection, so we swap in a no-op fake so the code
    // path runs without hitting the network.
    $this->pusherFake = new class extends PusherNotification
    {
        public array $sent = [];

        public function __construct() {}

        public function send(string $channel, string $event, array $payload, bool $compressedValue = false): void
        {
            $this->sent[] = compact('channel', 'event', 'payload');
        }
    };
    $this->app->instance(PusherNotification::class, $this->pusherFake);

    // InvoiceService::approveChanges/rejectChanges own the full write path
    // (invoice raw_data rewrite, transaction bookkeeping, email jobs). We
    // don't want to reproduce all of that in fixtures - this suite only
    // covers what THIS service adds: delegation + realtime bell notif +
    // guardrails. Mock the delegate to return a success envelope so we can
    // assert what the wrapper does on top.
    $this->invoiceServiceMock = mock(InvoiceService::class);
    $this->service = app(InvoiceChangeRequestService::class);

    cache()->flush();
});

// ---- List --------------------------------------------------------------

it('lists pending rows and includes status counts by default', function () {
    $deal = ProjectDeal::factory()->create();
    $invoice = Invoice::factory()->create(['project_deal_id' => $deal->id]);

    InvoiceRequestUpdate::factory()->count(2)->create([
        'invoice_id' => $invoice->id,
        'status' => InvoiceRequestUpdateStatus::Pending->value,
    ]);
    InvoiceRequestUpdate::factory()->create([
        'invoice_id' => $invoice->id,
        'status' => InvoiceRequestUpdateStatus::Approved->value,
    ]);
    InvoiceRequestUpdate::factory()->create([
        'invoice_id' => $invoice->id,
        'status' => InvoiceRequestUpdateStatus::Rejected->value,
    ]);

    actAsInvChgRole(BaseRole::Finance->value);

    $result = $this->service->list(status: 'pending');

    expect($result['code'])->toBe(201)
        ->and($result['data']['totalData'])->toBe(2)
        ->and(count($result['data']['paginated']))->toBe(2)
        ->and($result['data']['statusCount']['pending'])->toBe(2)
        ->and($result['data']['statusCount']['approved'])->toBe(1)
        ->and($result['data']['statusCount']['rejected'])->toBe(1)
        ->and($result['data']['statusCount']['all'])->toBe(4);
});

it('returns everything when status tab is not set', function () {
    $deal = ProjectDeal::factory()->create();
    $invoice = Invoice::factory()->create(['project_deal_id' => $deal->id]);

    InvoiceRequestUpdate::factory()->create([
        'invoice_id' => $invoice->id,
        'status' => InvoiceRequestUpdateStatus::Pending->value,
    ]);
    InvoiceRequestUpdate::factory()->create([
        'invoice_id' => $invoice->id,
        'status' => InvoiceRequestUpdateStatus::Approved->value,
    ]);

    actAsInvChgRole(BaseRole::Finance->value);

    $result = $this->service->list();

    expect($result['data']['totalData'])->toBe(2);
});

// ---- Approve -----------------------------------------------------------

it('approves a pending row, delegates to InvoiceService, notifies the requester', function () {
    $deal = ProjectDeal::factory()->create();
    $invoice = Invoice::factory()->create(['project_deal_id' => $deal->id]);

    // The requesting employee → user (bell recipient). The model's creating
    // hook stamps request_by from Auth::id(), so we actingAs them here.
    $requesterEmployee = Employee::factory()->withUser()->create();
    $requesterUser = User::where('employee_id', $requesterEmployee->id)->first();
    actingAs($requesterUser);

    $row = InvoiceRequestUpdate::factory()->create([
        'invoice_id' => $invoice->id,
        'status' => InvoiceRequestUpdateStatus::Pending->value,
    ]);

    actAsInvChgRole(BaseRole::Finance->value);

    // Delegate returns a success envelope; we're not exercising the
    // full write path here (that lives in InvoiceService's own tests).
    $this->invoiceServiceMock
        ->shouldReceive('approveChanges')
        ->once()
        ->withArgs(fn ($invoiceUid, $fromExternalUrl, $pendingUpdateId) =>
            $invoiceUid === $invoice->uid
            && $fromExternalUrl === false
            && (int) $pendingUpdateId === $row->id
        )
        ->andReturn(['error' => false, 'message' => 'ok', 'data' => [], 'code' => 201]);

    $result = $this->service->approve($row->id);

    expect($result['error'] ?? false)->toBeFalse();

    // Requester got the bell.
    expect($requesterUser->fresh()->notifications()->count())->toBe(1);

    $pushed = collect($this->pusherFake->sent)
        ->firstWhere('channel', "private-notifications.{$requesterUser->id}");
    expect($pushed)->not->toBeNull()
        ->and($pushed['payload']['action'])->toBe('invoice_changes_approved');
});

it('rejects a pending row with the given reason and notifies the requester', function () {
    $deal = ProjectDeal::factory()->create();
    $invoice = Invoice::factory()->create(['project_deal_id' => $deal->id]);

    $requesterEmployee = Employee::factory()->withUser()->create();
    $requesterUser = User::where('employee_id', $requesterEmployee->id)->first();
    actingAs($requesterUser);

    $row = InvoiceRequestUpdate::factory()->create([
        'invoice_id' => $invoice->id,
        'status' => InvoiceRequestUpdateStatus::Pending->value,
    ]);

    actAsInvChgRole(BaseRole::Finance->value);

    $this->invoiceServiceMock
        ->shouldReceive('rejectChanges')
        ->once()
        ->withArgs(fn ($payload, $invoiceUid, $fromExternalUrl, $pendingUpdateId) =>
            ($payload['reason'] ?? null) === 'Amount looks wrong'
            && $invoiceUid === $invoice->uid
            && $fromExternalUrl === false
            && (int) $pendingUpdateId === $row->id
        )
        ->andReturn(['error' => false, 'message' => 'ok', 'data' => [], 'code' => 201]);

    $result = $this->service->reject($row->id, reason: 'Amount looks wrong');

    expect($result['error'] ?? false)->toBeFalse();

    // Requester got a rejected bell with the reason in the message payload.
    $pushed = collect($this->pusherFake->sent)
        ->firstWhere('channel', "private-notifications.{$requesterUser->id}");
    expect($pushed)->not->toBeNull()
        ->and($pushed['payload']['action'])->toBe('invoice_changes_rejected')
        ->and($pushed['payload']['message'])->toContain('Amount looks wrong');
});

// ---- Guardrails --------------------------------------------------------

it('rejects an approve for an already-actioned row with 422', function () {
    $deal = ProjectDeal::factory()->create();
    $invoice = Invoice::factory()->create(['project_deal_id' => $deal->id]);
    $row = InvoiceRequestUpdate::factory()->create([
        'invoice_id' => $invoice->id,
        'status' => InvoiceRequestUpdateStatus::Approved->value,
    ]);

    actAsInvChgRole(BaseRole::Finance->value);

    $result = $this->service->approve($row->id);

    expect($result['code'])->toBe(422)
        ->and($result['error'])->toBeTrue();
});

it('returns 404 when the requested id does not exist', function () {
    actAsInvChgRole(BaseRole::Finance->value);

    $result = $this->service->approve(999999999);

    expect($result['code'])->toBe(404);
});
