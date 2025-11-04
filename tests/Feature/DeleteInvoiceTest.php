<?php

use App\Enums\Transaction\InvoiceStatus;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Modules\Finance\Jobs\InvoiceHasBeenDeletedJob;
use Modules\Finance\Models\Invoice;

use function Pest\Laravel\deleteJson;

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});

it('Delete invoice return success', function () {
    Bus::fake();

    $invoice = Invoice::factory()
        ->create([
            'status' => InvoiceStatus::Unpaid->value,
            'uid' => \Illuminate\Support\Str::uuid(),
            'parent_number' => 'IV/2025 - 950',
        ]);

    $invoiceData = Invoice::selectRaw('id,uid')
        ->find($invoice->id);

    $projectDealUid = Crypt::encryptString($invoice->project_deal_id);

    $response = deleteJson(route('api.invoices.destroy', ['invoice' => $invoiceData->uid, 'projectDealUid' => $projectDealUid]));

    $response->assertStatus(201);

    $this->assertDatabaseCount('invoices', 0);

    Bus::assertDispatched(InvoiceHasBeenDeletedJob::class);
});

it('Delete paid invoice', function () {
    Bus::fake();

    $invoice = Invoice::factory()
        ->create([
            'status' => InvoiceStatus::Paid->value,
            'uid' => \Illuminate\Support\Str::uuid(),
            'parent_number' => 'IV/2025 - 950',
        ]);

    $invoiceData = Invoice::selectRaw('id,uid')
        ->find($invoice->id);

    $projectDealUid = Crypt::encryptString($invoice->project_deal_id);

    $response = deleteJson(route('api.invoices.destroy', ['invoice' => $invoiceData->uid, 'projectDealUid' => $projectDealUid]));

    $response->assertStatus(400);

    $this->assertDatabaseCount('invoices', 1);

    expect($response->json())->toHaveKeys(['message']);

    expect($response->json()['message'])->toBe(__('notification.cannotDeletePaidInvoice'));

    Bus::assertNotDispatched(InvoiceHasBeenDeletedJob::class);
});