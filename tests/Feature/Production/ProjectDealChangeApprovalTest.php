<?php

use App\Enums\Production\ProjectDealChangePriceStatus;
use App\Enums\Production\ProjectDealChangeStatus;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\ProjectDealPriceChange;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectDeal;
use Modules\Production\Models\ProjectDealChange;
use Modules\Production\Models\ProjectQuotation;
use Modules\Production\Services\ProjectDealService;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

/**
 * Approval and rejection of the two independent change flows on a project deal - the deal-detail
 * changes (`project_deal_changes`) and the price changes (`project_deal_price_changes`) - plus the
 * deal-change listing.
 *
 * Both flows are reachable two ways: from the ERP (authenticated, permission-checked) and from a
 * signed link mailed to a director (no session, the actor arrives in the payload / query string).
 * Every decision is a one-shot state transition guarded on the record still being pending, so the
 * "already decided" paths matter as much as the happy ones.
 *
 * Note on jobs: the service dispatches with ->afterCommit(), and DatabaseTransactions keeps an outer
 * transaction open for the whole test, so those jobs never actually flush. These tests assert the
 * persisted state and the response envelope instead.
 */
function dealService(): ProjectDealService
{
    return app(ProjectDealService::class);
}

function actAsApprover(bool $withPermission = true): User
{
    $employee = Employee::factory()->withUser()->create();
    $user = User::where('employee_id', $employee->id)->firstOrFail();

    $permission = Permission::firstOrCreate([
        'name' => 'approve_project_deal_change',
        'guard_name' => 'sanctum',
    ]);

    if ($withPermission) {
        $user->givePermissionTo($permission);
    }

    actingAs($user);

    return $user;
}

function makeDealChange(array $attributes = []): ProjectDealChange
{
    return ProjectDealChange::factory()->create($attributes);
}

/**
 * A deal carrying the quotation and main invoice that approvePriceChanges rewrites.
 *
 * @return array{0: ProjectDeal, 1: Invoice}
 */
function makePricedDeal(int $currentPrice = 1000000): array
{
    $deal = ProjectDeal::factory()->create();

    ProjectQuotation::factory()->create([
        'project_deal_id' => $deal->id,
        'is_final' => 1,
        'fix_price' => $currentPrice,
    ]);

    $invoice = Invoice::factory()->create([
        'project_deal_id' => $deal->id,
        'is_main' => 1,
        'raw_data' => [
            'fixPrice' => 'Rp'.number_format($currentPrice, 0, ',', '.'),
            'remainingPayment' => 'Rp'.number_format($currentPrice, 0, ',', '.'),
        ],
    ]);

    return [$deal, $invoice];
}

/**
 * Crypt::encryptString uses a random IV, so the same id encrypts to a different
 * string every call - rows have to be located by decrypting their uid.
 */
function findDealChangeRow(array $response, int $changeId): ?object
{
    return collect($response['data']['paginated'])
        ->first(fn ($row) => Crypt::decryptString($row->uid) === (string) $changeId);
}

function makePriceChange(ProjectDeal $deal, array $attributes = []): ProjectDealPriceChange
{
    return ProjectDealPriceChange::factory()->create(array_merge([
        'project_deal_id' => $deal->id,
        'old_price' => 1000000,
        'new_price' => 2500000,
        'status' => ProjectDealChangePriceStatus::Pending->value,
    ], $attributes));
}

beforeEach(function () {
    Notification::fake();
});

describe('approveChangesProjectDeal', function () {
    it('applies the requested edit to the deal and the project and marks the change approved', function () {
        $approver = actAsApprover();
        $change = makeDealChange();

        $response = dealService()->approveChangesProjectDeal(Crypt::encryptString((string) $change->id));

        expect($response['error'])->toBeFalse();

        $change->refresh();
        expect($change->status)->toBe(ProjectDealChangeStatus::Approved)
            ->and($change->approval_by)->toBe($approver->id)
            ->and($change->approval_at)->not->toBeNull();

        expect(ProjectDeal::find($change->project_deal_id)->name)->toBe('Project Name Deal Update')
            ->and(Project::where('project_deal_id', $change->project_deal_id)->first()->name)
            ->toBe('Project Name Deal Update');
    });

    it('rewrites the quotation instead of the deal for quotation-scoped labels', function () {
        actAsApprover();
        $change = makeDealChange([
            'detail_changes' => [
                ['label' => 'Quotation Note', 'old_value' => 'old note', 'new_value' => 'new note'],
            ],
        ]);

        ProjectQuotation::factory()->create([
            'project_deal_id' => $change->project_deal_id,
            'is_final' => 1,
        ]);

        $response = dealService()->approveChangesProjectDeal(Crypt::encryptString((string) $change->id));

        expect($response['error'])->toBeFalse()
            ->and(ProjectQuotation::where('project_deal_id', $change->project_deal_id)->first()->description)
            ->toBe('new note');
    });

    it('ignores labels it does not know how to map', function () {
        actAsApprover();
        $change = makeDealChange([
            'detail_changes' => [
                ['label' => 'Something Unmapped', 'old_value' => 'a', 'new_value' => 'b'],
            ],
        ]);

        $originalName = ProjectDeal::find($change->project_deal_id)->name;

        $response = dealService()->approveChangesProjectDeal(Crypt::encryptString((string) $change->id));

        expect($response['error'])->toBeFalse()
            ->and(ProjectDeal::find($change->project_deal_id)->name)->toBe($originalName)
            ->and($change->refresh()->status)->toBe(ProjectDealChangeStatus::Approved);
    });

    it('takes the actor from the payload and skips the permission check when the request came from email', function () {
        $director = actAsApprover(withPermission: false);
        $change = makeDealChange();

        $response = dealService()->approveChangesProjectDeal(
            Crypt::encryptString((string) $change->id),
            ['approval_id' => $director->id]
        );

        expect($response['error'])->toBeFalse()
            ->and($change->refresh()->approval_by)->toBe($director->id);
    });

    it('refuses a web request from a user without the approval permission and leaves the change pending', function () {
        actAsApprover(withPermission: false);
        $change = makeDealChange();

        $response = dealService()->approveChangesProjectDeal(Crypt::encryptString((string) $change->id));

        expect($response['error'])->toBeTrue()
            ->and($response['code'])->toBe(403)
            ->and($change->refresh()->status)->toBe(ProjectDealChangeStatus::Pending);
    });

    it('reports not found for an id that matches no change', function () {
        actAsApprover();

        $response = dealService()->approveChangesProjectDeal(Crypt::encryptString('999999'));

        expect($response['error'])->toBeTrue()
            ->and($response['message'])->toBe(__('notification.dataNotFound'));
    });

    it('refuses to approve a change that was already approved', function () {
        actAsApprover();
        $change = makeDealChange(['status' => ProjectDealChangeStatus::Approved->value]);

        $response = dealService()->approveChangesProjectDeal(Crypt::encryptString((string) $change->id));

        expect($response['error'])->toBeTrue()
            ->and($response['message'])->toBe('Changes has already approved');
    });

    it('refuses to approve a change that was already rejected', function () {
        actAsApprover();
        $change = makeDealChange(['status' => ProjectDealChangeStatus::Rejected->value]);

        $originalName = ProjectDeal::find($change->project_deal_id)->name;

        $response = dealService()->approveChangesProjectDeal(Crypt::encryptString((string) $change->id));

        expect($response['error'])->toBeTrue()
            ->and($change->refresh()->status)->toBe(ProjectDealChangeStatus::Rejected)
            ->and(ProjectDeal::find($change->project_deal_id)->name)->toBe($originalName);
    });

    it('leaves no dangling transaction behind on any early return', function () {
        actAsApprover(withPermission: false);
        $approved = makeDealChange(['status' => ProjectDealChangeStatus::Approved->value]);
        $pending = makeDealChange();

        $level = DB::transactionLevel();

        dealService()->approveChangesProjectDeal(Crypt::encryptString('999999'));
        expect(DB::transactionLevel())->toBe($level);

        dealService()->approveChangesProjectDeal(Crypt::encryptString((string) $approved->id));
        expect(DB::transactionLevel())->toBe($level);

        dealService()->approveChangesProjectDeal(Crypt::encryptString((string) $pending->id));
        expect(DB::transactionLevel())->toBe($level);
    });
});

describe('rejectChangesProjectDeal', function () {
    it('marks the change rejected and records the rejecter', function () {
        $approver = actAsApprover();
        $change = makeDealChange();

        $response = dealService()->rejectChangesProjectDeal(Crypt::encryptString((string) $change->id));

        expect($response['error'])->toBeFalse();

        $change->refresh();
        expect($change->status)->toBe(ProjectDealChangeStatus::Rejected)
            ->and($change->rejected_by)->toBe($approver->id)
            ->and($change->rejected_at)->not->toBeNull();
    });

    it('never touches the deal itself when rejecting', function () {
        actAsApprover();
        $change = makeDealChange();
        $originalName = ProjectDeal::find($change->project_deal_id)->name;

        dealService()->rejectChangesProjectDeal(Crypt::encryptString((string) $change->id));

        expect(ProjectDeal::find($change->project_deal_id)->name)->toBe($originalName);
    });

    it('takes the actor from the payload when the request came from email', function () {
        $director = actAsApprover();
        $change = makeDealChange();

        dealService()->rejectChangesProjectDeal(
            Crypt::encryptString((string) $change->id),
            ['approval_id' => $director->id]
        );

        expect($change->refresh()->rejected_by)->toBe($director->id);
    });

    it('reports not found for an id that matches no change', function () {
        actAsApprover();

        $response = dealService()->rejectChangesProjectDeal(Crypt::encryptString('999999'));

        expect($response['error'])->toBeTrue()
            ->and($response['message'])->toBe(__('notification.dataNotFound'));
    });

    it('refuses to reject a change that was already approved', function () {
        actAsApprover();
        $change = makeDealChange(['status' => ProjectDealChangeStatus::Approved->value]);

        $response = dealService()->rejectChangesProjectDeal(Crypt::encryptString((string) $change->id));

        expect($response['error'])->toBeTrue()
            ->and($change->refresh()->status)->toBe(ProjectDealChangeStatus::Approved);
    });

    it('refuses to reject a change that was already rejected', function () {
        actAsApprover();
        $change = makeDealChange(['status' => ProjectDealChangeStatus::Rejected->value]);

        $response = dealService()->rejectChangesProjectDeal(Crypt::encryptString((string) $change->id));

        expect($response['error'])->toBeTrue();
    });

    it('leaves no dangling transaction behind on any early return', function () {
        actAsApprover();
        $decided = makeDealChange(['status' => ProjectDealChangeStatus::Approved->value]);

        $level = DB::transactionLevel();

        dealService()->rejectChangesProjectDeal(Crypt::encryptString('999999'));
        expect(DB::transactionLevel())->toBe($level);

        dealService()->rejectChangesProjectDeal(Crypt::encryptString((string) $decided->id));
        expect(DB::transactionLevel())->toBe($level);
    });
});

describe('approvePriceChanges', function () {
    it('pushes the new price onto the quotation and the main invoice and marks it approved', function () {
        $approver = actAsApprover();
        [$deal, $invoice] = makePricedDeal();
        $change = makePriceChange($deal, ['new_price' => 2500000]);

        $response = dealService()->approvePriceChanges((string) $change->id);

        expect($response['error'])->toBeFalse();

        $change->refresh();
        expect($change->status)->toBe(ProjectDealChangePriceStatus::Approved)
            ->and($change->approved_by)->toBe($approver->id)
            ->and($change->approved_at)->not->toBeNull();

        expect((float) ProjectQuotation::where('project_deal_id', $deal->id)->first()->fix_price)
            ->toBe(2500000.0);

        $raw = $invoice->refresh()->raw_data;
        expect($raw['fixPrice'])->toBe('Rp2.500.000')
            ->and($raw['remainingPayment'])->toBe('Rp2.500.000');
    });

    it('accepts the encrypted id carried by the emailed approval link', function () {
        actAsApprover();
        [$deal] = makePricedDeal();
        $change = makePriceChange($deal);

        $response = dealService()->approvePriceChanges(Crypt::encryptString((string) $change->id));

        expect($response['error'])->toBeFalse()
            ->and($change->refresh()->status)->toBe(ProjectDealChangePriceStatus::Approved);
    });

    it('takes the actor from the approvalId query parameter when there is no session', function () {
        $director = actAsApprover();
        [$deal] = makePricedDeal();
        $change = makePriceChange($deal);

        request()->merge(['approvalId' => $director->id]);

        dealService()->approvePriceChanges((string) $change->id);

        expect($change->refresh()->approved_by)->toBe($director->id);
    });

    it('reports not found for an id that matches no price change', function () {
        actAsApprover();

        $response = dealService()->approvePriceChanges('999999');

        expect($response['error'])->toBeTrue()
            ->and($response['message'])->toBe(__('notification.dataNotFound'));
    });

    it('refuses to approve a price change that was already approved', function () {
        actAsApprover();
        [$deal] = makePricedDeal();
        $change = makePriceChange($deal, ['status' => ProjectDealChangePriceStatus::Approved->value]);

        $response = dealService()->approvePriceChanges((string) $change->id);

        expect($response['error'])->toBeTrue()
            ->and($response['message'])->toBe(__('notification.priceChangesAlreadyDecided'));
    });

    it('refuses to approve a price change that was already rejected and leaves the quotation alone', function () {
        actAsApprover();
        [$deal] = makePricedDeal(1000000);
        $change = makePriceChange($deal, ['status' => ProjectDealChangePriceStatus::Rejected->value]);

        $response = dealService()->approvePriceChanges((string) $change->id);

        expect($response['error'])->toBeTrue()
            ->and((float) ProjectQuotation::where('project_deal_id', $deal->id)->first()->fix_price)
            ->toBe(1000000.0);
    });

    it('errors instead of half-applying when the deal has no main invoice', function () {
        actAsApprover();
        $deal = ProjectDeal::factory()->create();
        ProjectQuotation::factory()->create([
            'project_deal_id' => $deal->id,
            'is_final' => 1,
            'fix_price' => 1000000,
        ]);
        $change = makePriceChange($deal);

        $response = dealService()->approvePriceChanges((string) $change->id);

        expect($response['error'])->toBeTrue()
            ->and($response['message'])->toBe(__('notification.mainInvoiceNotFound'))
            ->and($change->refresh()->status)->toBe(ProjectDealChangePriceStatus::Pending)
            ->and((float) ProjectQuotation::where('project_deal_id', $deal->id)->first()->fix_price)
            ->toBe(1000000.0);
    });

    it('leaves no dangling transaction behind on any early return', function () {
        actAsApprover();
        [$deal] = makePricedDeal();
        $decided = makePriceChange($deal, ['status' => ProjectDealChangePriceStatus::Approved->value]);

        $level = DB::transactionLevel();

        dealService()->approvePriceChanges('999999');
        expect(DB::transactionLevel())->toBe($level);

        dealService()->approvePriceChanges((string) $decided->id);
        expect(DB::transactionLevel())->toBe($level);
    });
});

describe('rejectPriceChanges', function () {
    it('marks the price change rejected with the supplied reason', function () {
        $approver = actAsApprover();
        [$deal] = makePricedDeal();
        $change = makePriceChange($deal);

        $response = dealService()->rejectPriceChanges((string) $change->id, 'Client withdrew');

        expect($response['error'])->toBeFalse();

        $change->refresh();
        expect($change->status)->toBe(ProjectDealChangePriceStatus::Rejected)
            ->and($change->rejected_by)->toBe($approver->id)
            ->and($change->rejected_reason)->toBe('Client withdrew')
            ->and($change->rejected_at)->not->toBeNull();
    });

    it('falls back to a placeholder when no reason is given', function () {
        actAsApprover();
        [$deal] = makePricedDeal();
        $change = makePriceChange($deal);

        dealService()->rejectPriceChanges((string) $change->id);

        expect($change->refresh()->rejected_reason)->toBe('No reason provided');
    });

    it('never moves the price onto the quotation when rejecting', function () {
        actAsApprover();
        [$deal] = makePricedDeal(1000000);
        $change = makePriceChange($deal, ['new_price' => 9999999]);

        dealService()->rejectPriceChanges((string) $change->id);

        expect((float) ProjectQuotation::where('project_deal_id', $deal->id)->first()->fix_price)
            ->toBe(1000000.0);
    });

    it('accepts the encrypted id carried by the emailed rejection link', function () {
        actAsApprover();
        [$deal] = makePricedDeal();
        $change = makePriceChange($deal);

        $response = dealService()->rejectPriceChanges(Crypt::encryptString((string) $change->id));

        expect($response['error'])->toBeFalse()
            ->and($change->refresh()->status)->toBe(ProjectDealChangePriceStatus::Rejected);
    });

    it('reports not found for an id that matches no price change', function () {
        actAsApprover();

        $response = dealService()->rejectPriceChanges('999999');

        expect($response['error'])->toBeTrue()
            ->and($response['message'])->toBe(__('notification.dataNotFound'));
    });

    it('refuses to reject a price change that was already approved', function () {
        actAsApprover();
        [$deal] = makePricedDeal();
        $change = makePriceChange($deal, ['status' => ProjectDealChangePriceStatus::Approved->value]);

        $response = dealService()->rejectPriceChanges((string) $change->id);

        expect($response['error'])->toBeTrue()
            ->and($change->refresh()->status)->toBe(ProjectDealChangePriceStatus::Approved);
    });

    it('leaves no dangling transaction behind on any early return', function () {
        actAsApprover();
        [$deal] = makePricedDeal();
        $decided = makePriceChange($deal, ['status' => ProjectDealChangePriceStatus::Rejected->value]);

        $level = DB::transactionLevel();

        dealService()->rejectPriceChanges('999999');
        expect(DB::transactionLevel())->toBe($level);

        dealService()->rejectPriceChanges((string) $decided->id);
        expect(DB::transactionLevel())->toBe($level);
    });
});

describe('listProjectDealChanges', function () {
    it('returns the changes with their deal and requester', function () {
        $employee = Employee::factory()->withUser()->create(['name' => 'Wesley'])->refresh();
        $change = makeDealChange(['requested_by' => $employee->user_id]);

        request()->merge(['itemsPerPage' => 100]);

        $response = dealService()->listProjectDealChanges();
        $row = findDealChangeRow($response, $change->id);

        expect($response['error'])->toBeFalse()
            ->and($response['data']['totalData'])->toBeGreaterThanOrEqual(1)
            ->and($row)->not->toBeNull()
            ->and($row->request_by)->toBe('Wesley')
            ->and($row->event_name)->toBe(ProjectDeal::find($change->project_deal_id)->name);
    });

    it('renders a placeholder instead of failing when the change has no requester', function () {
        $change = makeDealChange(['requested_by' => null]);

        request()->merge(['itemsPerPage' => 100]);

        $response = dealService()->listProjectDealChanges();
        $row = findDealChangeRow($response, $change->id);

        expect($response['error'])->toBeFalse()
            ->and($row)->not->toBeNull()
            ->and($row->request_by)->toBe('-');
    });

    it('flags a pending change as actionable', function () {
        $change = makeDealChange();

        request()->merge(['itemsPerPage' => 100]);

        $row = findDealChangeRow(dealService()->listProjectDealChanges(), $change->id);

        expect($row->can_approve)->toBeTrue()
            ->and($row->can_reject)->toBeTrue()
            ->and($row->can_delete)->toBeTrue();
    });

    it('flags a decided change as no longer actionable', function (ProjectDealChangeStatus $status) {
        $change = makeDealChange(['status' => $status->value]);

        request()->merge(['itemsPerPage' => 100]);

        $row = findDealChangeRow(dealService()->listProjectDealChanges(), $change->id);

        expect($row->can_approve)->toBeFalse()
            ->and($row->can_reject)->toBeFalse()
            ->and($row->can_delete)->toBeFalse();
    })->with([
        'approved' => ProjectDealChangeStatus::Approved,
        'rejected' => ProjectDealChangeStatus::Rejected,
    ]);

    it('filters on the deal-change status requested', function () {
        $pending = makeDealChange();
        $approved = makeDealChange(['status' => ProjectDealChangeStatus::Approved->value]);
        $rejected = makeDealChange(['status' => ProjectDealChangeStatus::Rejected->value]);

        request()->merge(['status' => 'approved', 'itemsPerPage' => 100]);

        $uids = collect(dealService()->listProjectDealChanges()['data']['paginated'])
            ->pluck('uid')
            ->map(fn (string $uid) => Crypt::decryptString($uid))
            ->all();

        expect($uids)->toContain((string) $approved->id)
            ->not->toContain((string) $pending->id)
            ->not->toContain((string) $rejected->id);
    });

    it('returns every row when no status filter is given', function () {
        $pending = makeDealChange();
        $approved = makeDealChange(['status' => ProjectDealChangeStatus::Approved->value]);

        request()->merge(['itemsPerPage' => 100]);

        $uids = collect(dealService()->listProjectDealChanges()['data']['paginated'])
            ->pluck('uid')
            ->map(fn (string $uid) => Crypt::decryptString($uid))
            ->all();

        expect($uids)->toContain((string) $pending->id)
            ->toContain((string) $approved->id);
    });

    it('honours itemsPerPage while reporting the unpaged total', function () {
        makeDealChange();
        makeDealChange();

        request()->merge(['itemsPerPage' => 1]);

        $response = dealService()->listProjectDealChanges();

        expect($response['data']['paginated'])->toHaveCount(1)
            ->and($response['data']['totalData'])->toBeGreaterThanOrEqual(2);
    });
});
