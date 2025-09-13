<?php

use App\Enums\Interactive\InteractiveRequestStatus;
use App\Enums\Production\ProjectDealStatus;
use Modules\Production\Models\InteractiveRequest;
use Modules\Production\Models\ProjectDeal;

beforeEach(function () {
    $this->user = initAuthenticateUser();

    $this->actingAs($this->user);
});

it('Reject interactive request', function () {
    $name = 'Project ok jos';
    $price = 100000000;

    $project = ProjectDeal::factory()
        ->withQuotation(price: $price)
        ->withInvoice(rawData: [
            'fixPrice' => 'Rp100,000,000',
            'remainingPayment' => 'Rp100,000,000',
            'transactions' => [],
            'led' => json_decode('[{"name":"main","total":"4 m<sup>2<\/sup>","textDetail":"2 x 2 m","led":[{"width":"2","height":"2"}]},{"name":" prefunction","total":"1 m<sup>2<\/sup>","textDetail":"1 x 1 m","led":[{"width":"1","height":"1"}]}]', true),
        ])
        ->create([
            'name' => $name,
            'status' => ProjectDealStatus::Final->value,
        ]);

    // create first interactive request
    $interactiveLed = json_decode('[{"name":"interactive","led":[{"width":"1","height":"1"}],"total":"1 m<sup>2<\/sup>","totalRaw":"1","textDetail":"1 x 1 m"}]', true);
    $project->interactiveRequests()->create([
        'status' => InteractiveRequestStatus::Pending,
        'interactive_detail' => $interactiveLed,
        'interactive_area' => 1,
        'interactive_note' => 'This is interactive note',
        'interactive_fee' => 5000000,
        'fix_price' => 105000000,
    ]);

    $this->assertDatabaseHas('project_deals', [
        'id' => $project->id,
        'interactive_note' => null,
        'interactive_detail' => null,
        'interactive_area' => 0,
    ]);

    $requestData = InteractiveRequest::where('project_deal_id', $project->id)->first();

    $response = $this->getJson(route('api.production.interactives.reject').'?requestId='.$requestData->id);

    $response->assertStatus(201);

    $this->assertDatabaseHas('interactive_requests', [
        'project_deal_id' => $project->id,
        'interactive_area' => 1,
        'interactive_note' => 'This is interactive note',
        'interactive_fee' => '5000000.00',
        'requester_id' => $this->user->id,
        'status' => InteractiveRequestStatus::Rejected->value,
        'rejected_by' => $this->user->id,
    ]);

    $this->assertDatabaseHas('project_deals', [
        'id' => $project->id,
        'interactive_note' => null,
        'interactive_detail' => null,
        'interactive_area' => 0,
    ]);

    $this->assertDatabaseCount('interactive_projects', 0);
});
