<?php

use App\Actions\GenerateQuotationNumber;
use App\Enums\Production\ProjectDealStatus;
use App\Services\Geocoding;
use App\Services\NasFolderCreationService;
use Illuminate\Support\Facades\Bus;
use Modules\Finance\Jobs\ProjectHasBeenFinal;
use Modules\Production\Jobs\AddInteractiveProjectJob;
use Modules\Production\Models\Customer;
use Modules\Production\Models\ProjectDeal;
use Modules\Production\Repository\ProjectQuotationRepository;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->user = initAuthenticateUser();

    $this->actingAs($this->user);
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

        // include tax
        $requestData['include_tax'] = true;

        // change status
        $requestData['status'] = ProjectDealStatus::Draft->value;

        // change name
        $requestData['name'] = 'Draft project';

        $response = postJson('/api/production/project/deals', $requestData);

        $response->assertStatus(201);
        assertDatabaseCount('project_deals', 1);
        assertDatabaseHas('project_deals', [
            'name' => 'Draft project',
            'identifier_number' => '0951',
            'include_tax' => 1,
        ]);
        assertDatabaseMissing('projects', [
            'name' => 'Draft project',
        ]);
        assertDatabaseCount('project_quotations', 1);
        assertDatabaseCount('project_deal_marketings', 1);
        assertDatabaseCount('transactions', 0);
    })->with([
        fn () => Customer::factory()->create(),
    ]);

    it('Create final project deal directly', function (Customer $customer) {
        Bus::fake();

        // $nasService = Mockery::mock(NasFolderCreationService::class);
        // $nasService->shouldReceive('sendRequest')
        //     ->withAnyArgs()
        //     ->andReturn(true);

        $requestData = getProjectDealPayload($customer);
        $requestData = prepareProjectDeal($requestData);

        // set to final
        $requestData['status'] = ProjectDealStatus::Final->value;

        // change name
        $requestData['name'] = 'Final Project';

        // modify quotation id
        $requestData['quotation']['quotation_id'] = 'DF0010';
        $requestData['quotation']['is_final'] = 1;

        $geoMockup = Mockery::mock(Geocoding::class);
        $geoMockup->shouldReceive('getCoordinate')
            ->andReturn([
                'longitude' => 106.816666,
                'latitude' => -6.200000,
            ]);

        $service = createProjectService(
            geoCoding: $geoMockup,
        );

        $response = $service->storeProjectDeals(payload: $requestData);

        expect($response)->toHaveKey('error');
        expect($response['error'])->toBeFalse();
        expect($response['data'])->toHaveKey('url');

        $currentDeal = ProjectDeal::select('id', 'published_by', 'published_at')->where('name', $requestData['name'])->first();

        assertDatabaseHas('project_deals', [
            'name' => 'Final Project',
            'identifier_number' => '0951',
        ]);

        expect($currentDeal->published_by)->toBe($this->user->id);
        expect($currentDeal->published_at)->not->toBeNull();

        assertDatabaseHas('projects', [
            'name' => 'Final Project',
            'project_deal_id' => $currentDeal->id,
        ]);
        assertDatabaseCount('invoices', 1);
        assertDatabaseHas('invoices', [
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

        // $nasService = Mockery::mock(NasFolderCreationService::class);
        // $nasService->shouldReceive('sendRequest')
        //     ->withAnyArgs()
        //     ->andReturn(true);

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

        assertDatabaseCount('project_quotations', 2);
        assertDatabaseHas('project_deals', [
            'name' => $nameOne,
            'id' => $dealOne->id,
        ]);
        assertDatabaseHas('project_deals', [
            'name' => $nameTwo,
            'id' => $dealTwo->id,
        ]);
        assertDatabaseHas('project_quotations', [
            'project_deal_id' => $dealOne->id,
            'quotation_id' => $dealOne->latestQuotation->quotation_id,
        ]);
        assertDatabaseHas('project_quotations', [
            'project_deal_id' => $dealTwo->id,
            'quotation_id' => $dealTwo->latestQuotation->quotation_id,
        ]);
        assertDatabaseMissing('project_quotations', [
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

        // $nasService = Mockery::mock(NasFolderCreationService::class);
        // $nasService->shouldReceive('sendRequest')
        //     ->withAnyArgs()
        //     ->andReturn(true);

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

        $response->assertStatus(201);
        assertDatabaseCount('project_deals', 1);
        assertDatabaseHas('project_deals', [
            'name' => 'Project with Interactive Element',
        ]);
        assertDatabaseCount('projects', 1);
        assertDatabaseHas('projects', [
            'name' => 'Project with Interactive Element',
        ]);
        assertDatabaseCount('interactive_projects', 1);
        assertDatabaseHas('interactive_projects', [
            'name' => 'Project with Interactive Element',
            'note' => 'This is interactive note',
            'led_area' => $requestData['interactive_area'],
        ]);
        assertDatabaseCount('project_quotations', 1);
        assertDatabaseCount('project_deal_marketings', 1);
        assertDatabaseCount('interactive_project_boards', 3);

        Bus::assertDispatched(ProjectHasBeenFinal::class);
    })->with([
        fn () => Customer::factory()->create(),
    ]);

    it('Create temporary project deal with interactive element', function (Customer $customer) {
        Bus::fake();

        $requestData = getProjectDealPayload($customer);
        $requestData = prepareProjectDeal($requestData);

        // $nasService = Mockery::mock(NasFolderCreationService::class);
        // $nasService->shouldReceive('sendRequest')
        //     ->withAnyArgs()
        //     ->andReturn(true);

        // change name
        $requestData['name'] = 'Project with Interactive Element';

        // set to final
        $requestData['status'] = ProjectDealStatus::Temporary->value;

        // modify quotation id
        $requestData['quotation']['quotation_id'] = 'DF0010';

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
        $requestData['interactive_fee'] = '50000000';

        $response = postJson(route('api.production.project-deal.store'), $requestData);

        $response->assertStatus(201);
        assertDatabaseCount('project_deals', 1);
        assertDatabaseHas('project_deals', [
            'name' => 'Project with Interactive Element',
        ]);
        assertDatabaseCount('projects', 0);
        assertDatabaseCount('interactive_projects', 0);
        assertDatabaseHas('interactive_requests', [
            'project_deal_id' => ProjectDeal::first()->id,
        ]);
        assertDatabaseCount('project_quotations', 1);
        assertDatabaseCount('project_deal_marketings', 1);

        Bus::assertNotDispatched(ProjectHasBeenFinal::class);
        Bus::assertDispatched(AddInteractiveProjectJob::class);
    })->with([
        fn () => Customer::factory()->create(),
    ]);
});
