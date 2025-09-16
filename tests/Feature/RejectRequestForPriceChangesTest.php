<?php

use App\Enums\Production\ProjectDealChangePriceStatus;
use App\Enums\Production\ProjectDealStatus;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
use Modules\Finance\Jobs\NotifyRequestPriceChangesHasBeenApproved;
use Modules\Finance\Models\ProjectDealPriceChange;
use Modules\Production\Models\ProjectDeal;

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});

function seedDataReject()
{
    $oldPrice = 150000000;
    $newPrice = 160000000;
    $projectDeal = ProjectDeal::factory()
        ->withQuotation($oldPrice)
        ->withInvoice(1, [
            'fixPrice' => 'Rp'.number_format($oldPrice, 0, ',', '.'),
            'remainingPayment' => 'Rp'.number_format($oldPrice, 0, ',', '.'),
        ])
        ->create([
            'status' => ProjectDealStatus::Final->value,
        ]);

    $currentProjectDeal = ProjectDeal::with([
        'latestQuotation',
    ])->find($projectDeal->id);

    $change = ProjectDealPriceChange::factory()->create([
        'project_deal_id' => $projectDeal->id,
        'old_price' => $oldPrice,
        'new_price' => $newPrice,
    ]);

    return [
        'projectDeal' => $projectDeal,
        'currentProjectDeal' => $currentProjectDeal,
        'change' => $change,
        'oldPrice' => $oldPrice,
        'newPrice' => $newPrice,
    ];
}

it('should reject request for price changes', function () {
    Bus::fake();

    $data = seedDataReject();
    $oldPrice = $data['oldPrice'];
    $projectDeal = $data['projectDeal'];

    $reason = 'Price changes rejected due to budget constraints.';

    $response = $this->postJson(route('api.finance.rejectPriceChanges', [
        'changeId' => Crypt::encryptString($data['change']->id),
        'projectDealUid' => Crypt::encryptString($projectDeal->id),
    ]), [
        'reason' => $reason,
    ]);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'message',
    ]);

    $this->assertDatabaseHas('project_deal_price_changes', [
        'id' => $data['change']->id,
        'status' => ProjectDealChangePriceStatus::Rejected->value,
        'rejected_reason' => $reason,
        'approved_at' => null,
        'approved_by' => null,
    ]);

    $this->assertDatabaseHas('project_quotations', [
        'fix_price' => $oldPrice,
        'project_deal_id' => $projectDeal->id,
    ]);

    Bus::assertDispatched(NotifyRequestPriceChangesHasBeenApproved::class);
});

it('should reject request from email return success', function () {
    Bus::fake();

    $data = seedDataReject();
    $oldPrice = $data['oldPrice'];
    $projectDeal = $data['projectDeal'];

    $reason = 'Price changes rejected due to budget constraints.';

    // build URL
    $rejectionUrl = URL::temporarySignedRoute(
        'project.deal.change.price.reject',
        now()->addMinutes(30),
        [
            'priceChangeId' => Crypt::encryptString($data['change']->id),
        ]
    );

    $response = $this->get($rejectionUrl);

    $response->assertViewIs('invoices.rejected');
    $response->assertViewHas('title', 'Event Changes Rejected');
    $response->assertViewHas('message', 'Price changes rejected successfully.');

    $this->assertDatabaseHas('project_deal_price_changes', [
        'id' => $data['change']->id,
        'status' => ProjectDealChangePriceStatus::Rejected->value,
        'rejected_reason' => 'No reason provided',
        'approved_at' => null,
        'approved_by' => null,
    ]);

    $this->assertDatabaseHas('project_quotations', [
        'fix_price' => $oldPrice,
        'project_deal_id' => $projectDeal->id,
    ]);

    Bus::assertDispatched(NotifyRequestPriceChangesHasBeenApproved::class);
});
