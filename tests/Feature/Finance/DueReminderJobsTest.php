<?php

use App\Models\User;
use App\Services\GeneralService;
use App\Services\PusherNotification;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Notification;
use Modules\Finance\Jobs\InvoiceDueCheck;
use Modules\Finance\Notifications\InvoiceDueCheckNotification;
use Modules\Hrd\Models\Employee;
use Modules\Production\Jobs\PaymentDueReminderJob;
use Modules\Production\Models\ProjectDeal;
use Modules\Production\Models\ProjectDealMarketing;
use Modules\Production\Notifications\PaymentDueReminderNotification;
use Spatie\Permission\Models\Role;

/**
 * Regression coverage for the two scheduler jobs that were failing and clogging the
 * queue in dev:
 *   - PaymentDueReminderNotification called generateInvoiceNumber() with no argument
 *     (ArgumentCountError).
 *   - PaymentDueReminderJob / InvoiceDueCheck called notify() on a null employee/user.
 */

/**
 * Swap PusherNotification for a no-op so InvoiceDueCheck never hits the network in tests.
 */
function fakePusher(): void
{
    app()->instance(PusherNotification::class, new class extends PusherNotification
    {
        public function __construct() {}

        public function send(string $channel, string $event, array $payload, bool $compressedValue = false): void {}
    });
}

describe('PaymentDueReminderNotification', function () {
    it('builds the invoice number from the deal identifier (no ArgumentCountError)', function () {
        $deal = ProjectDeal::factory()->create();

        $notification = new PaymentDueReminderNotification($deal);

        $expected = app(GeneralService::class)->generateInvoiceNumber(identifierNumber: $deal->identifier_number);

        expect($notification->invoiceNumber)->toBe($expected)
            ->and($notification->invoiceNumber)->toContain($deal->identifier_number);
    });
});

describe('PaymentDueReminderJob', function () {
    it('notifies marketing employees and skips a marketing with no employee', function () {
        Notification::fake();

        $employee = Employee::factory()->create();
        $deal = ProjectDeal::factory()->create();
        ProjectDealMarketing::create(['project_deal_id' => $deal->id, 'employee_id' => $employee->id]);

        // A marketing whose employee has since been removed -> the relation resolves to null.
        $ghost = Employee::factory()->create();
        ProjectDealMarketing::create(['project_deal_id' => $deal->id, 'employee_id' => $ghost->id]);
        $ghost->delete();

        $deal->load('marketings.employee');

        $service = Mockery::mock(GeneralService::class)->makePartial();
        $service->shouldReceive('getUpcomingPaymentDue')->andReturn(new EloquentCollection([$deal]));
        app()->instance(GeneralService::class, $service);

        (new PaymentDueReminderJob)->handle();

        Notification::assertSentTo($employee, PaymentDueReminderNotification::class);
        Notification::assertCount(1); // the null-employee marketing was skipped, not fatal
    });
});

describe('InvoiceDueCheck', function () {
    it('notifies finance/root users and skips a marketing that has no user account', function () {
        Notification::fake();
        fakePusher();

        Role::firstOrCreate(['name' => 'root', 'guard_name' => 'sanctum']);
        Role::firstOrCreate(['name' => 'finance', 'guard_name' => 'sanctum']);

        $rootEmployee = Employee::factory()->create();
        $root = User::factory()->create(['employee_id' => $rootEmployee->id]);
        $root->assignRole('root');

        // A due invoice whose deal has a marketing pointing to an employee with no user.
        $invoices = new EloquentCollection([
            (object) ['projectDeal' => (object) ['marketings' => collect([(object) ['employee_id' => 999999]])]],
        ]);

        $service = Mockery::mock(GeneralService::class)->makePartial();
        $service->shouldReceive('getInvoiceDueData')->andReturn($invoices);
        app()->instance(GeneralService::class, $service);

        (new InvoiceDueCheck)->handle();

        Notification::assertSentTo($root, InvoiceDueCheckNotification::class);
    });
});
