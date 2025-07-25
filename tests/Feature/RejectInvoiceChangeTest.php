<?php

use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\InvoiceRequestUpdate;

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});

describe('Reject invoice changes', function () {
    it('Reject changes return success', function () {
        $firstPaymentDate = now()->addDays(30)->format('Y-m-d');
        $firstPaymentDue = now()->parse($firstPaymentDate)->addDays(7)->format('Y-m-d');
        $firstInvoice = Invoice::factory()->create([
            'amount' => 20000000,
            'payment_date' => $firstPaymentDate,
            'raw_data' => [
                'fixPrice' => "Rp50,000,000",
                'remainingPayment' => "Rp30,000,000",
                'trxDate' => date('d F Y', strtotime($firstPaymentDate)),
                'paymentDue' => date('d F Y', strtotime($firstPaymentDue)),
                'transactions' => [
                    [
                        'id' => null,
                        'payment' => "Rp20,000,000",
                        'transaction_date' => date('d F Y', strtotime($firstPaymentDate)),
                    ]
                ]
            ]
        ]);

        $change = InvoiceRequestUpdate::factory()->create([
            'invoice_id' => $firstInvoice->id,
            'amount' => 25000000,
            'status' => \App\Enums\Finance\InvoiceRequestUpdateStatus::Pending
        ]);

        $invoice = Invoice::selectRaw('uid,id')->find($change->invoice_id);

        $service = setInvoiceService();
        $response = $service->rejectChanges(payload: ['reason' => 'Nothing'], invoiceUid: $invoice->uid, pendingUpdateId: $change->id);

        expect($response['error'])->toBeFalse();

        $this->assertDatabaseHas('invoice_request_updates', [
            'id' => $change->id,
            'status' => \App\Enums\Finance\InvoiceRequestUpdateStatus::Rejected->value
        ]);

        $this->assertDatabaseMissing('invoice_request_updates', [
            'id' => $change->id,
            'status' => \App\Enums\Finance\InvoiceRequestUpdateStatus::Pending->value,
            'rejected_by' => null,
            'rejected_at' => null,
            'reason' => null
        ]);

        // check invoices status in invoices table, it should be have unpaid status
        $this->assertDatabaseHas('invoices', [
            'id' => $firstInvoice->id,
            'status' => \App\Enums\Transaction\InvoiceStatus::Unpaid->value
        ]);
    });

    it ('Reject invoice that already rejected before', function() {
        $firstPaymentDate = now()->addDays(30)->format('Y-m-d');
        $firstPaymentDue = now()->parse($firstPaymentDate)->addDays(7)->format('Y-m-d');
        $firstInvoice = Invoice::factory()->create([
            'amount' => 20000000,
            'payment_date' => $firstPaymentDate,
            'raw_data' => [
                'fixPrice' => "Rp50,000,000",
                'remainingPayment' => "Rp30,000,000",
                'trxDate' => date('d F Y', strtotime($firstPaymentDate)),
                'paymentDue' => date('d F Y', strtotime($firstPaymentDue)),
                'transactions' => [
                    [
                        'id' => null,
                        'payment' => "Rp20,000,000",
                        'transaction_date' => date('d F Y', strtotime($firstPaymentDate)),
                    ]
                ]
            ]
        ]);

        $change = InvoiceRequestUpdate::factory()->create([
            'invoice_id' => $firstInvoice->id,
            'amount' => 25000000,
            'status' => \App\Enums\Finance\InvoiceRequestUpdateStatus::Rejected
        ]);

        $invoice = Invoice::selectRaw('uid,id')->find($change->invoice_id);

        $service = setInvoiceService();
        $response = $service->rejectChanges(payload: ['reason' => 'Nothing'], invoiceUid: $invoice->uid, pendingUpdateId: $change->id);

        expect($response['error'])->toBeTrue();

        expect($response['message'])->toBe(__('notification.noChangesToApprove'));
    });
});
