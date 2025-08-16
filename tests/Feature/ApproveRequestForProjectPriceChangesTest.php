<?php

use App\Enums\Production\ProjectDealChangePriceStatus;
use App\Enums\Production\ProjectDealStatus;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
use Modules\Finance\Jobs\NotifyRequestPriceChangesHasBeenApproved;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\ProjectDealPriceChange;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\ProjectDeal;

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});

function seedData() {
    $oldPrice = 150000000;
    $newPrice = 160000000;
    $projectDeal = ProjectDeal::factory()
        ->withQuotation($oldPrice)
        ->withInvoice(1, [
            'fixPrice' => "Rp" . number_format($oldPrice, 0, ',', '.'),
            'remainingPayment' => "Rp" . number_format($oldPrice, 0, ',', '.'),
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

it('Approve changes from system return success', function () {
    Bus::fake();

    $data = seedData();
    $projectDeal = $data['projectDeal'];
    $currentProjectDeal = $data['currentProjectDeal'];
    $change = $data['change'];
    $oldPrice = $data['oldPrice'];
    $newPrice = $data['newPrice'];

    $changeId = Crypt::encryptString($change->id);

    $response = $this->getJson(route('api.finance.approvePriceChanges', [
        'projectDealUid' => Crypt::encryptString($projectDeal->id),
        'changeId' => $changeId,
    ]));
    
    $response->assertStatus(201);

    $this->assertDatabaseHas('project_deal_price_changes', [
        'id' => $change->id,
        'status' => ProjectDealChangePriceStatus::Approved->value,
    ]);

    // project deal quotation price
    $this->assertDatabaseHas('project_quotations', [
        'id' => $currentProjectDeal->latestQuotation->id,
        'fix_price' => $newPrice,
    ]);

    // check invoice raw data
    $formattedNewPrice = "Rp" . number_format($newPrice, 0, ',', '.');
    $invoice = Invoice::select('id', 'raw_data')
        ->where('project_deal_id', $projectDeal->id)
        ->first();

    $rawData = $invoice->raw_data;

    $this->assertEquals($formattedNewPrice, $rawData['fixPrice']);
    $this->assertEquals($formattedNewPrice, $rawData['remainingPayment']);

    Bus::assertDispatched(NotifyRequestPriceChangesHasBeenApproved::class);
});

it('Approve changes from email and return success', function () {
    Bus::fake();
    $data = seedData();
    $projectDeal = $data['projectDeal'];
    $change = $data['change'];
    $oldPrice = $data['oldPrice'];
    $newPrice = $data['newPrice'];
    $currentProjectDeal = $data['currentProjectDeal'];

    $changeId = Crypt::encryptString($change->id);

    // create director
    $employee = Employee::factory()
        ->withUser()
        ->create();

    $approvalUrl = URL::temporarySignedRoute(
        'project.deal.change.price.approve',
        now()->addMinutes(30),
        [
            'priceChangeId' => $changeId,
            'approvalId' => $employee->user_id
        ]
    );

    // fetch approval URL
    $response = $this->get($approvalUrl);

    $this->assertDatabaseHas('project_deal_price_changes', [
        'id' => $change->id,
        'status' => ProjectDealChangePriceStatus::Approved->value,
    ]);

    // project deal quotation price
    $this->assertDatabaseHas('project_quotations', [
        'id' => $currentProjectDeal->latestQuotation->id,
        'fix_price' => $newPrice,
    ]);

    // check invoice raw data
    $formattedNewPrice = "Rp" . number_format($newPrice, 0, ',', '.');
    $invoice = Invoice::select('id', 'raw_data')
        ->where('project_deal_id', $projectDeal->id)
        ->first();

    $rawData = $invoice->raw_data;

    $this->assertEquals($formattedNewPrice, $rawData['fixPrice']);
    $this->assertEquals($formattedNewPrice, $rawData['remainingPayment']);

    Bus::assertDispatched(NotifyRequestPriceChangesHasBeenApproved::class);
});