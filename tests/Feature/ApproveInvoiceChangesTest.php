<?php

use Illuminate\Support\Facades\Bus;
use Modules\Finance\Jobs\ApproveInvoiceChangesJob;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\InvoiceRequestUpdate;

test('Approve changes return success', function () {
    Bus::fake();

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
        'status' => \App\Enums\Finance\InvoiceRequestUpdateStatus::Pending->value
    ]);

    $invoice = Invoice::selectRaw('uid,id')->find($change->invoice_id);

    $service = setInvoiceService();
    $response = $service->approveChanges(invoiceUid: $invoice->uid);
    
    expect($response['error'])->toBeFalse();

    $this->assertDatabaseHas('invoice_request_updates', [
        'id' => $change->id,
        'status' => \App\Enums\Finance\InvoiceRequestUpdateStatus::Approved->value
    ]);

    $this->assertDatabaseMissing('invoice_request_updates', [
        'id' => $change->id,
        'status' => \App\Enums\Finance\InvoiceRequestUpdateStatus::Pending->value,
        'approved_by' => null,
        'approved_at' => null,
    ]);
    
    // check changes in main invoices table
    $this->assertDatabaseHas('invoices', [
        'id' => $invoice->id,
        'amount' => $change->amount,
        'payment_date' => $firstInvoice->payment_date,
        'status' => \App\Enums\Transaction\InvoiceStatus::Unpaid->value
    ]);

    $invoiceAfterChanges = Invoice::selectRaw('id,raw_data')->find($change->invoice_id);
    
    $rawData = $invoiceAfterChanges->raw_data;
    $fixPrice = str_replace(['Rp', ','], '', $rawData['fixPrice']);
    $remainingPayment = $fixPrice - $change->amount;
    expect($rawData['remainingPayment'])->toBe("Rp" . number_format(num: $remainingPayment, decimal_separator: ','));

    // check raw data transaction
    $transactions = $rawData['transactions'];
    expect($transactions[0]['payment'])->toBe("Rp" . number_format(num: $change->amount, decimal_separator: ','));

    Bus::assertDispatched(ApproveInvoiceChangesJob::class);
});

it('Approve changes that already approved before', function () {
    $firstPaymentDate = now()->addDays(30)->format('Y-m-d');
    $firstPaymentDue = now()->parse($firstPaymentDate)->addDays(7)->format('Y-m-d');
    
    // create approved changes by run the factor
    $invoiceData = Invoice::factory()->create([
        'status' => \App\Enums\Transaction\InvoiceStatus::Unpaid->value,
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
        'status' => \App\Enums\Finance\InvoiceRequestUpdateStatus::Approved->value,
        'invoice_id' => $invoiceData->id,
    ]);

    $invoice = Invoice::selectRaw('uid,id')->find($change->invoice_id);

    $service = setInvoiceService();
    $response = $service->approveChanges(invoiceUid: $invoice->uid);
    logging('RRESPONSE CHANGE INVOICE APPROVE BEFORE', $response);
    expect($response['error'])->toBeTrue();

    expect($response['message'])->toBe(__('notification.noChangesToApprove'));
});

it('Approve changes that already other request history for the same invoice id', function () {
    $firstPaymentDate = now()->addDays(30)->format('Y-m-d');
    $firstPaymentDue = now()->parse($firstPaymentDate)->addDays(7)->format('Y-m-d');

    $invoice = Invoice::factory()->create([
        'status' => \App\Enums\Transaction\InvoiceStatus::Unpaid->value,
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

    InvoiceRequestUpdate::factory()
        ->create([
            'invoice_id' => $invoice->id,
            'status' => \App\Enums\Finance\InvoiceRequestUpdateStatus::Approved->value
        ]);

    InvoiceRequestUpdate::factory()
        ->create([
            'invoice_id' => $invoice->id,
            'status' => \App\Enums\Finance\InvoiceRequestUpdateStatus::Pending->value,
            'amount' => $invoice->amount + 200000
        ]);

    $service = setInvoiceService();
    $response = $service->approveChanges(invoiceUid: $invoice->uid);
        logging('RRESPONSE CHANGE INVOICE DOUBLE', $response);
    expect($response['error'])->toBeFalse();

    $this->assertDatabaseHas('invoices', [
        'invoice_id' => $invoice->id,
        'status' => \App\Enums\Transaction\InvoiceStatus::Unpaid->value
    ]);
});