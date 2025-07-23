<?php

use App\Enums\Transaction\InvoiceStatus;
use Illuminate\Support\Facades\Crypt;
use Modules\Finance\Models\Invoice;

use function Pest\Laravel\{getJson, postJson, withHeaders, actingAs};
use function PHPUnit\Framework\assertArrayHasKey;

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});

it('Request invoice test with no changes in it', function () {
    $invoice = Invoice::factory()
        ->create([
            'status' => InvoiceStatus::Unpaid->value
        ]);

    $invoiceId = Crypt::encryptString($invoice->id);

    $response = postJson(
        uri: route('api.invoices.updateTemporaryData', ['invoiceId' => $invoiceId, 'projectDealUid' => $invoice->project_deal_id]),
        data: [
            'amount' => $invoice->amount,
            'transaction_date' => date('Y-m-d', strtotime($invoice->payment_date))
        ]
    );

    logging("RESPONSE UPDATE INVOICE CHANGES", $response->json());

    $response->assertStatus(422);
    assertArrayHasKey('errors', $response->json());
    assertArrayHasKey('amount', $response->json()['errors']);
});
