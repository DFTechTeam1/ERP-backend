<?php

use App\Actions\GenerateQuotationNumber;
use App\Enums\Production\ProjectDealStatus;
use Illuminate\Support\Facades\Bus;
use Modules\Finance\Jobs\ProjectHasBeenFinal;
use Modules\Production\Models\Customer;
use Modules\Production\Models\ProjectDeal;
use Modules\Production\Repository\ProjectQuotationRepository;

use function Pest\Laravel\postJson;

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});

describe('Create Project Deal', function () {

    it('Create Project Return Failed', function () {
        $response = postJson('/api/production/project/deals', []);
        $response->assertStatus(422);

        expect($response->json())->toHaveKey('errors');
    });

    it('Create Deals Return Success', function (Customer $customer) {
        $requestData = getProjectDealPayload($customer);
        $requestData = prepareProjectDeal($requestData);

        // change status
        $requestData['status'] = ProjectDealStatus::Draft->value;

        // change name
        $requestData['name'] = 'Draft project';

        $response = postJson('/api/production/project/deals', $requestData);

        $response->assertStatus(201);
        $this->assertDatabaseCount('project_deals', 1);
        $this->assertDatabaseHas('project_deals', [
            'name' => 'Draft project',
            'identifier_number' => '0951',
        ]);
        $this->assertDatabaseMissing('projects', [
            'name' => 'Draft project',
        ]);
        $this->assertDatabaseCount('project_quotations', 1);
        $this->assertDatabaseCount('project_deal_marketings', 1);
        $this->assertDatabaseCount('transactions', 0);
    })->with([
        fn () => Customer::factory()->create(),
    ]);

    it('Create final project deal directly', function (Customer $customer) {
        Bus::fake();

        $requestData = getProjectDealPayload($customer);
        $requestData = prepareProjectDeal($requestData);

        // set to final
        $requestData['status'] = ProjectDealStatus::Final->value;

        // change name
        $requestData['name'] = 'Final Project';

        // modify quotation id
        $requestData['quotation']['quotation_id'] = 'DF0010';
        $requestData['quotation']['is_final'] = 1;

        $service = createProjectService();

        $response = $service->storeProjectDeals(payload: $requestData);

        expect($response)->toHaveKey('error');
        expect($response['error'])->toBeFalse();
        expect($response['data'])->toHaveKey('url');

        $currentDeal = ProjectDeal::select('id')->where('name', $requestData['name'])->first();

        $this->assertDatabaseHas('project_deals', [
            'name' => 'Final Project',
            'identifier_number' => '0951',
        ]);
        $this->assertDatabaseHas('projects', [
            'name' => 'Final Project',
            'project_deal_id' => $currentDeal->id,
        ]);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseHas('invoices', [
            'is_main' => 1,
            'parent_number' => null,
            'sequence' => 0,
            'status' => \App\Enums\Transaction\InvoiceStatus::Unpaid->value,
            'paid_amount' => 0,
        ]);

        Bus::assertDispatched(ProjectHasBeenFinal::class);
    })->with([
        fn () => Customer::factory()->create(),
    ]);

    it('Create project deal when 2 people access in the same time', function (Customer $customer) {
        // we assume two people request quotation number when in the same time
        $output = collect([
            [
                'quotation_id' => 'DF01100',
            ],
        ]);

        $mock = Mockery::mock(ProjectQuotationRepository::class);
        $mock->shouldReceive('list')
            ->withAnyArgs()
            ->andReturn($output);

        $quotationOne = GenerateQuotationNumber::run($mock);
        $quotationTwo = GenerateQuotationNumber::run($mock);

        $requestData = getProjectDealPayload($customer);
        $requestData = prepareProjectDeal($requestData);

        $requestDataTwo = getProjectDealPayload($customer);
        $requestDataTwo = prepareProjectDeal($requestDataTwo);

        // change name
        $nameOne = 'Final Project';
        $nameTwo = 'Final Project Two';
        $requestData['name'] = $nameOne;
        $requestDataTwo['name'] = $nameTwo;

        // modify quotation id
        $requestData['quotation']['quotation_id'] = $quotationOne;
        $requestDataTwo['quotation']['quotation_id'] = $quotationOne;
        $requestData['quotation']['is_final'] = 1;

        $service = createProjectService();

        $service->storeProjectDeals(payload: $requestData);
        $service->storeProjectDeals(payload: $requestDataTwo);

        // here we check, quotation id should be different even they store the same quotation id
        $dealOne = ProjectDeal::selectRaw('id')
            ->with(['latestQuotation'])
            ->where('name', $nameOne)
            ->first();
        $dealTwo = ProjectDeal::selectRaw('id')
            ->with(['latestQuotation'])
            ->where('name', $nameTwo)
            ->first();

        $this->assertDatabaseCount('project_quotations', 2);
        $this->assertDatabaseHas('project_deals', [
            'name' => $nameOne,
            'id' => $dealOne->id,
        ]);
        $this->assertDatabaseHas('project_deals', [
            'name' => $nameTwo,
            'id' => $dealTwo->id,
        ]);
        $this->assertDatabaseHas('project_quotations', [
            'project_deal_id' => $dealOne->id,
            'quotation_id' => $dealOne->latestQuotation->quotation_id,
        ]);
        $this->assertDatabaseHas('project_quotations', [
            'project_deal_id' => $dealTwo->id,
            'quotation_id' => $dealTwo->latestQuotation->quotation_id,
        ]);
        $this->assertDatabaseMissing('project_quotations', [
            'project_deal_id' => $dealOne->id,
            'quotation_id' => $dealTwo->latestQuotation->quotation_id,
        ]);
    })->with([
        fn () => Customer::factory()->create(),
    ]);

    it('Create project deal with interactive element', function (Customer $customer) {
        Bus::fake();

        $requestData = getProjectDealPayload($customer);
        $requestData = prepareProjectDeal($requestData);

        // change name
        $requestData['name'] = 'Project with Interactive Element';

        // set to final
        $requestData['status'] = ProjectDealStatus::Final->value;

        // modify quotation id
        $requestData['quotation']['quotation_id'] = 'DF0010';
        $requestData['quotation']['is_final'] = 1;

        // set interactive element
        $requestData['interactive_area'] = 92;
        $requestData['interactive_detail'] = [
            [
                'name' => 'main',
                'led' => [
                    [
                        'height' => '10',
                        'width' => '5',
                    ],
                    [
                        'height' => '5',
                        'width' => '4',
                    ],
                ],
                'total' => '70 m<sup>2</sup>',
                'totalRaw' => '70',
                'textDetail' => '5 x 10 m , 4 x 5 m',
            ],
            [
                'name' => 'prefunction',
                'led' => [
                    [
                        'height' => '3',
                        'width' => '3',
                    ],
                    [
                        'height' => '3',
                        'width' => '2',
                    ],
                    [
                        'height' => '5',
                        'width' => '4',
                    ],
                ],
                'total' => '35 m<sup>2</sup>',
                'totalRaw' => '35',
                'textDetail' => '3 x 3 m , 2 x 3 m4 x 5 m',
            ],
        ];
        $requestData['interactive_note'] = 'This is interactive note';

        $response = postJson(route('api.production.project-deal.store'), $requestData);

        logging('RESPONSE INTERACTIVE ELEMENT', $response->json());

        $response->assertStatus(201);
        $this->assertDatabaseCount('project_deals', 1);
        $this->assertDatabaseHas('project_deals', [
            'name' => 'Project with Interactive Element',
        ]);
        $this->assertDatabaseCount('projects', 1);
        $this->assertDatabaseHas('projects', [
            'name' => 'Project with Interactive Element',
        ]);
        $this->assertDatabaseCount('interactive_projects', 1);
        $this->assertDatabaseHas('interactive_projects', [
            'name' => 'Project with Interactive Element',
            'note' => 'This is interactive note',
            'led_area' => $requestData['interactive_area'],
        ]);
        $this->assertDatabaseCount('project_quotations', 1);
        $this->assertDatabaseCount('project_deal_marketings', 1);
        $this->assertDatabaseCount('interactive_project_boards', 3);

        Bus::assertDispatched(ProjectHasBeenFinal::class);
    })->with([
        fn () => Customer::factory()->create(),
    ]);
});
