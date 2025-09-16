<?php

use App\Enums\Production\ProjectDealStatus;
use App\Enums\Transaction\InvoiceStatus;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Modules\Finance\Jobs\NotifyRequestPriceChangesJob;
use Modules\Finance\Models\Invoice;

beforeEach(function () {
    $this->user = initAuthenticateUser(withEmployee: true);

    $this->actingAs($this->user);
});

it('can request price changes', function () {
    Bus::fake();

    // seed reason for price changes
    $reason = \Modules\Finance\Models\PriceChangeReason::factory()->create([
        'name' => 'Need to adjust the budget',
    ]);

    $projectDeal = \Modules\Production\Models\ProjectDeal::factory()
        ->withQuotation()
        ->withInvoice()
        ->create([
            'status' => ProjectDealStatus::Final->value,
        ]);

    $payload = [
        'reason_id' => $reason->id,
        'price' => 1000,
    ];

    $response = $this->postJson(route('api.finance.requestPriceChanges', ['projectDealUid' => Crypt::encryptString($projectDeal->id)]), $payload);

    $response->assertStatus(201);
    $response->assertJson([
        'message' => __('notification.requestPriceChangesSuccess'),
    ]);

    // custom_reason on project_deal_price_changes should be null
    $this->assertDatabaseHas('project_deal_price_changes', [
        'project_deal_id' => $projectDeal->id,
        'reason_id' => $reason->id,
        'custom_reason' => null,
        'new_price' => 1000,
        'requested_by' => $this->user->id,
    ]);

    Bus::assertDispatched(NotifyRequestPriceChangesJob::class);
});

it('Request price changes when already have multiple invoices', function () {
    $price = 100000000;

    $projectDeal = \Modules\Production\Models\ProjectDeal::factory()
        ->withQuotation(price: $price)
        ->create([
            'status' => ProjectDealStatus::Final->value,
        ]);

    Invoice::factory()->create([
        'project_deal_id' => $projectDeal->id,
        'status' => InvoiceStatus::Unpaid->value,
        'amount' => 100000000,
        'raw_data' => [
            'fixPrice' => 'Rp100,000,000',
            'remainingPayment' => 'Rp100,000,000',
            'transactions' => [],
        ],
    ]);
    Invoice::factory()->create([
        'project_deal_id' => $projectDeal->id,
        'status' => InvoiceStatus::Unpaid->value,
        'amount' => 20000000,
        'raw_data' => [
            'fixPrice' => 'Rp100,000,000',
            'remainingPayment' => 'Rp80,000,000',
            'transactions' => [
                [
                    'id' => 1,
                    'payment' => 'Rp20,000,000',
                    'transaction_date' => '05 September 2025',
                ],
            ],
        ],
    ]);

    $payload = [
        'custom_reason' => 'Need to adjust the budget',
        'reason_id' => 0,
        'price' => 120000000,
    ];

    $response = $this->postJson(route('api.finance.requestPriceChanges', ['projectDealUid' => Crypt::encryptString($projectDeal->id)]), $payload);

    $response->assertStatus(201);

    $this->assertDatabaseHas('project_deal_price_changes', [
        'project_deal_id' => $projectDeal->id,
        'custom_reason' => 'Need to adjust the budget',
        'reason_id' => 0,
        'new_price' => 120000000,
        'requested_by' => $this->user->id,
    ]);
});
