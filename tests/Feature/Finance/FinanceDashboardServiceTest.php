<?php

use App\Enums\Finance\InvoiceRequestUpdateStatus;
use App\Enums\Production\ProjectDealChangePriceStatus;
use App\Enums\System\BaseRole;
use App\Models\User;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\InvoiceRequestUpdate;
use Modules\Finance\Models\ProjectDealPriceChange;
use Modules\Finance\Services\FinanceDashboardService;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\ProjectDeal;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;

/**
 * Coverage for the Finance dashboard pending-queue endpoints.
 *
 * Rules under test:
 *   - Finance, Director, Root can call.
 *   - Every other role gets 403.
 *   - Only Pending rows come back (Approved/Rejected excluded).
 *   - Newest first.
 *   - Row shape carries the fields the dashboard renders.
 */

function ensureFinanceRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name, 'guard_name' => 'sanctum']);
}

function actAsFinanceRole(string $roleName): User
{
    ensureFinanceRole($roleName);
    $employee = Employee::factory()->create();
    $user = User::factory()->create(['employee_id' => $employee->id]);
    $user->assignRole($roleName);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    actingAs($user);

    return $user;
}

beforeEach(function () {
    $this->service = app(FinanceDashboardService::class);
    cache()->flush();
});

// ---- Invoice update queue -------------------------------------------------

it('forbids non-finance-privileged roles from reading pending invoice updates', function () {
    actAsFinanceRole(BaseRole::Sales->value);

    $result = $this->service->getPendingInvoiceUpdates();

    expect($result['code'])->toBe(403);
});

it('returns only pending invoice updates, newest first', function () {
    $deal = ProjectDeal::factory()->create(['name' => 'Deal A']);
    $invoice = Invoice::factory()->create(['project_deal_id' => $deal->id]);

    $older = InvoiceRequestUpdate::factory()->create([
        'invoice_id' => $invoice->id,
        'amount' => 5_000_000,
        'status' => InvoiceRequestUpdateStatus::Pending->value,
    ]);
    $newer = InvoiceRequestUpdate::factory()->create([
        'invoice_id' => $invoice->id,
        'amount' => 8_000_000,
        'status' => InvoiceRequestUpdateStatus::Pending->value,
    ]);
    // Rejected + approved should be excluded.
    InvoiceRequestUpdate::factory()->create([
        'invoice_id' => $invoice->id,
        'status' => InvoiceRequestUpdateStatus::Approved->value,
    ]);
    InvoiceRequestUpdate::factory()->create([
        'invoice_id' => $invoice->id,
        'status' => InvoiceRequestUpdateStatus::Rejected->value,
    ]);

    actAsFinanceRole(BaseRole::Finance->value);

    $result = $this->service->getPendingInvoiceUpdates();
    $items = collect($result['data']['items']);
    $ids = $items->pluck('id')->all();

    expect($result['data']['total'])->toBe(2)
        ->and($ids)->toBe([$newer->id, $older->id])
        ->and($items->first()['requested_amount'])->toBe(8_000_000.0)
        ->and($items->first()['project_name'])->toBe('Deal A');
});

it('caps limit at 50 and floors at 1 on pending invoice updates', function () {
    $invoice = Invoice::factory()->create();
    InvoiceRequestUpdate::factory()->count(3)->create([
        'invoice_id' => $invoice->id,
        'status' => InvoiceRequestUpdateStatus::Pending->value,
    ]);

    actAsFinanceRole(BaseRole::Finance->value);

    $capped = $this->service->getPendingInvoiceUpdates(limit: 5000);
    $floored = $this->service->getPendingInvoiceUpdates(limit: 0);

    // Total is unaffected by limit; only items count is limited.
    expect($capped['data']['total'])->toBe(3)
        ->and(count($capped['data']['items']))->toBe(3)
        ->and(count($floored['data']['items']))->toBe(1);
});

// ---- Price change queue ---------------------------------------------------

it('forbids non-finance-privileged roles from reading pending price changes', function () {
    actAsFinanceRole(BaseRole::Sales->value);

    $result = $this->service->getPendingPriceChanges();

    expect($result['code'])->toBe(403);
});

it('returns only pending price changes with delta computed', function () {
    $deal = ProjectDeal::factory()->create(['name' => 'Deal B']);

    $pending = ProjectDealPriceChange::factory()->create([
        'project_deal_id' => $deal->id,
        'old_price' => 100_000_000,
        'new_price' => 120_000_000,
        'status' => ProjectDealChangePriceStatus::Pending->value,
    ]);
    ProjectDealPriceChange::factory()->create([
        'project_deal_id' => $deal->id,
        'status' => ProjectDealChangePriceStatus::Approved->value,
    ]);
    ProjectDealPriceChange::factory()->create([
        'project_deal_id' => $deal->id,
        'status' => ProjectDealChangePriceStatus::Rejected->value,
    ]);

    actAsFinanceRole(BaseRole::Finance->value);

    $result = $this->service->getPendingPriceChanges();

    expect($result['data']['total'])->toBe(1);

    $item = $result['data']['items'][0];
    expect($item['id'])->toBe($pending->id)
        ->and($item['old_price'])->toBe(100_000_000.0)
        ->and($item['new_price'])->toBe(120_000_000.0)
        ->and($item['delta'])->toBe(20_000_000.0)
        ->and($item['project_name'])->toBe('Deal B');
});

it('allows Director and Root to preview both queues', function () {
    $invoice = Invoice::factory()->create();
    InvoiceRequestUpdate::factory()->create([
        'invoice_id' => $invoice->id,
        'status' => InvoiceRequestUpdateStatus::Pending->value,
    ]);
    ProjectDealPriceChange::factory()->create([
        'status' => ProjectDealChangePriceStatus::Pending->value,
    ]);

    foreach ([BaseRole::Director->value, BaseRole::Root->value] as $roleName) {
        actAsFinanceRole($roleName);

        $inv = $this->service->getPendingInvoiceUpdates();
        $pc = $this->service->getPendingPriceChanges();

        expect($inv['code'])->toBe(201, "invoice queue for {$roleName}")
            ->and($pc['code'])->toBe(201, "price-change queue for {$roleName}")
            ->and($inv['data']['total'])->toBeGreaterThanOrEqual(1)
            ->and($pc['data']['total'])->toBeGreaterThanOrEqual(1);
    }
});
