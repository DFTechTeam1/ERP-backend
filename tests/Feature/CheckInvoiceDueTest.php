<?php

use App\Enums\Transaction\InvoiceStatus;
use App\Services\GeneralService;
use Modules\Finance\Models\Invoice;

it('No invoice due found', function () {
    $invoice = Invoice::factory()
        ->create([
            'payment_date' => now()->addDays(7)->format('Y-m-d'),
            'payment_due' => now()->addDays(14)->format('Y-m-d'),
        ]);

    $service = new GeneralService;

    $response = $service->getInvoiceDueData();

    expect($response->count())->toBe(0);
    $this->assertDatabaseHas('invoices', [
        'id' => $invoice->id,
    ]);
    $this->assertDatabaseCount('invoices', 1);
});

it('Due invoice detected', function () {
    $invoice = Invoice::factory()
        ->create([
            'payment_date' => now()->subDays(4)->format('Y-m-d'),
            'payment_due' => now()->addDays(3)->format('Y-m-d'),
            'status' => InvoiceStatus::Unpaid->value,
        ]);

    $service = new GeneralService;

    $response = $service->getInvoiceDueData();

    expect($response->count())->toBe(1);
    $this->assertDatabaseHas('invoices', [
        'id' => $invoice->id,
    ]);
    $this->assertDatabaseCount('invoices', 1);
});
