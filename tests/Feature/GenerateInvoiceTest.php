<?php

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Modules\Finance\Models\Transaction;
use Modules\Production\Models\ProjectDeal;
use Modules\Production\Models\ProjectQuotation;

it('GenerateInvoiceBill', function(ProjectDeal $projectDeal) {
    $service = setTransactionService();

    Storage::fake();

    // set filename
    

    $response = $service->downloadInvoice(
        payload: [
            "uid" => Crypt::encryptString($projectDeal->id),
            'type' => 'bill',
            'amount' => 20000000,
            'date' => '2025-08-01',
            'output' => 'download'
        ]
    );
})->with('ProjectDeal');

dataset(name: 'ProjectDeal', dataset: [
    fn() => [
        ProjectDeal::factory()
            ->has(ProjectQuotation::factory()->state([
                'is_final' => 1,
                'fix_price' => 100000000
            ]))
            ->has(factory: Transaction::factory()->count(3), relationship: 'transactions')
            ->create()
    ]
]);