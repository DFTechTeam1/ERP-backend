<?php

use App\Enums\Production\ProjectDealStatus;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Modules\Finance\Jobs\NotifyRequestPriceChangesJob;

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

it('cannot request price changes if project deal has child invoices or transactions', function () {
    $projectDeal = \Modules\Production\Models\ProjectDeal::factory()
        ->withQuotation()
        ->withInvoice(2)
        ->create([
            'status' => ProjectDealStatus::Final->value,
        ]);

    $payload = [
        'reason_id' => 'Need to adjust the budget',
        'price' => 1000,
    ];

    $response = $this->postJson(route('api.finance.requestPriceChanges', ['projectDealUid' => Crypt::encryptString($projectDeal->id)]), $payload);

    $response->assertStatus(400);
    $response->assertJson([
        'message' => __('notification.projectDealHasChildInvoicesOrTransactions'),
    ]);
});
