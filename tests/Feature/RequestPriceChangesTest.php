<?php

use App\Enums\Production\ProjectDealStatus;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Modules\Finance\Jobs\NotifyRequestPriceChangesJob;
use Modules\Production\Jobs\NotifyApprovalProjectDealChangeJob;

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});

it('can request price changes', function () {
    Bus::fake();
    $projectDeal = \Modules\Production\Models\ProjectDeal::factory()
        ->withQuotation()
        ->withInvoice()
        ->create([
            'status' => ProjectDealStatus::Final->value,
        ]);

    $payload = [
        'reason' => 'Need to adjust the budget',
        'price' => 1000,
    ];

    $response = $this->postJson(route('api.finance.requestPriceChanges', ['projectDealUid' => Crypt::encryptString($projectDeal->id)]), $payload);
    
    $response->assertStatus(201);
    $response->assertJson([
        'message' => __('notification.requestPriceChangesSuccess'),
    ]);

    Bus::assertDispatched(NotifyRequestPriceChangesJob::class);
});

it ('cannot request price changes if project deal has child invoices or transactions', function () {
    $projectDeal = \Modules\Production\Models\ProjectDeal::factory()
        ->withQuotation()
        ->withInvoice(2)
        ->create([
            'status' => ProjectDealStatus::Final->value,
        ]);

    $payload = [
        'reason' => 'Need to adjust the budget',
        'price' => 1000,
    ];

    $response = $this->postJson(route('api.finance.requestPriceChanges', ['projectDealUid' => Crypt::encryptString($projectDeal->id)]), $payload);
    
    $response->assertStatus(400);
    $response->assertJson([
        'message' => __('notification.projectDealHasChildInvoicesOrTransactions'),
    ]);
});
