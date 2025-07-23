<?php

use App\Enums\Finance\InvoiceRequestUpdateStatus;
use App\Enums\Transaction\InvoiceStatus;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Modules\Finance\Jobs\RequestInvoiceChangeJob;
use Modules\Finance\Models\Invoice;

use function Pest\Laravel\{getJson, postJson, withHeaders, actingAs, putJson};
use function PHPUnit\Framework\assertArrayHasKey;
use function PHPUnit\Framework\assertEquals;

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});

it('Request invoice test with no changes in it', function () {
    Bus::fake();

    $invoice = Invoice::factory()
        ->create([
            'status' => InvoiceStatus::Unpaid->value
        ]);

    $response = postJson(
        uri: route('api.invoices.updateTemporaryData', ['projectDealUid' => $invoice->project_deal_id]),
        data: [
            'amount' => $invoice->amount,
            'payment_date' => date('Y-m-d', strtotime($invoice->payment_date)),
            'invoice_uid' => $invoice->uid
        ]
    );

    $response->assertStatus(422);
    assertArrayHasKey('errors', $response->json());
    assertArrayHasKey('amount', $response->json()['errors']);
    assertEquals('No changes are submitted', $response->json()['errors']['amount'][0]);

    Bus::assertNotDispatched(RequestInvoiceChangeJob::class);
});

it('Request invoice test with changes in amount', function () {
    Bus::fake();

    $invoice = Invoice::factory()
        ->create([
            'status' => InvoiceStatus::Unpaid->value,
            'amount' => 15000000
        ]);
    $response = postJson(
        uri: route('api.invoices.updateTemporaryData', ['projectDealUid' => $invoice->project_deal_id]),
        data: [
            'amount' => 17000000,
            'invoice_uid' => $invoice->uid
        ]
    );

    $response->assertStatus(201);
    
    $this->assertDatabaseHas('invoice_request_updates', [
        'amount' => '17000000.00',
        'payment_date' => null,
        'invoice_id' => $invoice->id,
        'status' => InvoiceRequestUpdateStatus::Pending->value,
        'request_by' => auth()->id()
    ]);

    Bus::assertDispatched(RequestInvoiceChangeJob::class);
});

it('Request invoice test with changes in payment date', function () {
    Bus::fake();

    $changesDate = now()->addDays(3)->format('Y-m-d');
    $invoice = Invoice::factory()
        ->create([
            'status' => InvoiceStatus::Unpaid->value,
            'amount' => 15000000
        ]);
    $response = postJson(
        uri: route('api.invoices.updateTemporaryData', ['projectDealUid' => $invoice->project_deal_id]),
        data: [
            'payment_date' => $changesDate,
            'invoice_uid' => $invoice->uid
        ]
    );

    $response->assertStatus(201);
    
    $this->assertDatabaseHas('invoice_request_updates', [
        'amount' => null,
        'payment_date' => $changesDate,
        'invoice_id' => $invoice->id,
        'status' => InvoiceRequestUpdateStatus::Pending->value,
        'request_by' => auth()->id()
    ]);

    Bus::assertDispatched(RequestInvoiceChangeJob::class);
});
