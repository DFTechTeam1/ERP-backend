<?php

// api.production.project-deal.addInteractive

use App\Enums\Interactive\InteractiveRequestStatus;
use App\Enums\Production\ProjectDealStatus;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Modules\Production\Jobs\AddInteractiveProjectJob;
use Modules\Production\Models\ProjectDeal;

beforeEach(function () {
    $this->user = initAuthenticateUser();

    $this->actingAs($this->user);
});

it('Adding interactive in final project deal that do not have any invoices yet', function () {
    Bus::fake();

    $name = 'Project new ok';
    $price = 100000000;

    $project = ProjectDeal::factory()
        ->withQuotation(price: $price)
        ->withInvoice(rawData: [
            'fixPrice' => 'Rp100,000,000',
            'remainingPayment' => 'Rp100,000,000',
            'led' => json_decode('[{"name":"main","total":"4 m<sup>2<\/sup>","textDetail":"2 x 2 m","led":[{"width":"2","height":"2"}]},{"name":" prefunction","total":"1 m<sup>2<\/sup>","textDetail":"1 x 1 m","led":[{"width":"1","height":"1"}]}]', true),
        ])
        ->create([
            'name' => $name,
            'status' => ProjectDealStatus::Final->value,
        ]);

    $payload = [
        'interactive_detail' => json_decode('[{"name":"interactive","led":[{"width":"1","height":"1"}],"total":"1 m<sup>2<\/sup>","totalRaw":"1","textDetail":"1 x 1 m"}]', true),
        'interactive_area' => 1,
        'interactive_note' => 'This is interactive note',
        'interactive_fee' => 5000000,
        'fix_price' => 105000000,
    ];

    $projectUid = Crypt::encryptString($project->id);
    $response = $this->postJson(route('api.production.project-deal.addInteractive', ['projectDealUid' => $projectUid]), $payload);

    $response->assertStatus(201);

    $this->assertDatabaseHas('interactive_requests', [
        'project_deal_id' => $project->id,
        'interactive_area' => 1,
        'interactive_note' => 'This is interactive note',
        'interactive_fee' => '5000000.00',
        'requester_id' => $this->user->id,
        'status' => InteractiveRequestStatus::Pending->value,
    ]);

    Bus::assertDispatched(AddInteractiveProjectJob::class);
});

it('Adding interactive in final project deal that already have interactive request', function () {
    $name = 'Project new ok';
    $price = 100000000;

    $project = ProjectDeal::factory()
        ->withQuotation(price: $price)
        ->create([
            'name' => $name,
            'status' => ProjectDealStatus::Final->value,
        ]);

    // create first interactive request
    $project->interactiveRequests()->create([
        'status' => InteractiveRequestStatus::Pending,
        'interactive_detail' => json_decode('[{"name":"interactive","led":[{"width":"1","height":"1"}],"total":"1 m<sup>2<\/sup>","totalRaw":"1","textDetail":"1 x 1 m"}]', true),
        'interactive_area' => 1,
        'interactive_note' => 'This is interactive note',
        'interactive_fee' => 5000000,
        'fix_price' => 105000000,
        'requester_id' => $this->user->id,
    ]);

    $payload = [
        'interactive_detail' => json_decode('[{"name":"interactive","led":[{"width":"1","height":"1"}],"total":"1 m<sup>2<\/sup>","totalRaw":"1","textDetail":"1 x 1 m"}]', true),
        'interactive_area' => 1,
        'interactive_note' => 'This is interactive note',
        'interactive_fee' => 5000000,
        'fix_price' => 105000000,
    ];

    $projectUid = Crypt::encryptString($project->id);
    $response = $this->postJson(route('api.production.project-deal.addInteractive', ['projectDealUid' => $projectUid]), $payload);

    $response->assertStatus(400);
    $response->assertJson([
        'message' => __('notification.eventAlreadyHaveInteractiveRequest'),
    ]);

    $this->assertDatabaseCount('interactive_requests', 1);
});
