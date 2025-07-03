<?php

use App\Services\GeneralService;
use Illuminate\Http\UploadedFile;
use Modules\Company\Models\City;
use Modules\Company\Models\Country;
use Modules\Company\Models\State;
use Modules\Production\Models\ProjectQuotation;

use function Pest\Laravel\{getJson, postJson, withHeaders, actingAs};

beforeEach(function () {
    $this->actingAs(initAuthenticateUser());
});

$requestData = [
    'name' => 'Project Testing',
    'project_date' => '2025-06-30',
    'customer_id' => 1,
    'event_type' => 'wedding',
    'venue' => 'Grand Hall',
    'collaboration' => null,
    'note' => null,
    'led_area' => 110,
    'led_detail' => [
        [
            'name' => 'main',
            'led' => [
                [
                    'height' => '5.5',
                    'width' => '20'
                ]
            ],
            'total' => '110 m<sup>2</sup>',
            'totalRaw' => '110',
            'textDetail' => '20 x 5.5 m'
        ]
    ],
    'country_id' => '102',
    'state_id' => '1827',
    'city_id' => '56803',
    'project_class_id' => 1,
    'longitude' => null,
    'latitude' => null,
    'equipment_type' => 'lasika',
    'is_high_season' => 1,
    'client_portal' => 'wedding-anniversary',
    'marketing_id' => [
        'f063164d-62ff-44cf-823d-7c456dad1f4b'
    ],
    'status' => 1, // 1 is active, 0 is draft
    'quotation' => [
        'quotation_id' => 'DF04022',
        'is_final' => 1,
        'event_location_guide' => 'surabaya',
        'main_ballroom' => 72000000,
        'prefunction' => 10000000,
        'high_season_fee' => 2500000,
        'equipment_fee' => 0,
        'sub_total' => 84500000,
        'maximum_discount' => 5000000,
        'total' => 84500000,
        'maximum_markup_price' => 90000000,
        'fix_price' => 85000000,
        'is_high_season' => 1,
        'equipment_type' => 'lasika',
        'items' => [1, 2],
        'description' => '',
        'design_job' => 1
    ],
    'request_type' => 'save_and_download' // will be draft,save,save_and_download
];

function getEmptyPayload() {
    return [
        'payment_amount' => '',
        'transaction_date' => '',
        'note' => '',
        'reference' => '',
        'images' => [],
    ];
}

function getPayload() {
    $file = UploadedFile::fake()->image('testing.jpg');

    return [
        'payment_amount' => 10000000,
        'transaction_date' => '2025-07-01',
        'note' => '',
        'reference' => '',
        'images' => [
            [
                'image' => $file
            ]
        ],
    ];
}

describe('Create Transaction', function () use ($requestData) {
    it('Create transaction return failed', function () {
        $payload = getEmptyPayload();

        $response = $this->postJson('/api/finance/transaction/quotationId/projectDealUid', $payload);
        
        $response->assertStatus(422);
        expect($response->json())->toHaveKey('errors');
    });

    it('Create Transaction With invalid encryption quotationId', function () {
        $payload = getPayload();

        $response = $this->postJson('/api/finance/transaction/quotationId/projectDealUid', $payload);
        $response->assertStatus(400);

        expect($response->json())->toHavekey('message');
        expect($response->json()['message'])->toContain('The payload is invalid');
    });

    it("Quotation not found", function () {
        $payload = getPayload();
        $encrypted = \Illuminate\Support\Facades\Crypt::encryptString('password');

        $response = $this->postJson('/api/finance/transaction/' . $encrypted . '/projectDealUid', $payload);
        $response->assertStatus(400);

        expect($response->json())->toHavekey('message');
        expect($response->json()['message'])->toContain('Quotation is not found');
    });

    it("Payment amount greater than remaining amount", function () use($requestData) {
        $payload = getPayload();
        $payload['payment_amount'] = 90000000;
        
        // create deal
        $requestData = prepareProjectDeal($requestData);

        $country = Country::factory()
        ->has(
            State::factory()
                ->has(City::factory())
        )
        ->create();
        $requestData['country_id'] = $country->id;
        $requestData['state_id'] = $country->states[0]->id;
        $requestData['city_id'] = $country->states[0]->cities[0]->id;

        $responseDeal = postJson('/api/production/project/deals', $requestData);

        $encrypted = \Illuminate\Support\Facades\Crypt::encryptString($requestData['quotation']['quotation_id']);

        // mocking
        $client = Mockery::mock(GeneralService::class);
        $client->shouldReceive('uploadImageandCompress')
            ->withAnyArgs()
            ->andReturn('image.webp');

        $response = postJson('/api/finance/transaction/' . $encrypted . '/projectDealUid', $payload);

        $response->assertStatus(400);
        expect($response->json())->toHaveKey('message');
        expect($response->json()['message'])->toContain(__('notification.paymentAmountShouldBeSmallerThanRemainingAmount'));
    });

    it("Fully paid transaction", function () use ($requestData) {
        $payload = getPayload();
        $payload['payment_amount'] = $requestData['quotation']['fix_price'];

        // create deal
        $requestData = prepareProjectDeal($requestData);

        $country = Country::factory()
        ->has(
            State::factory()
                ->has(City::factory())
        )
        ->create();
        $requestData['country_id'] = $country->id;
        $requestData['state_id'] = $country->states[0]->id;
        $requestData['city_id'] = $country->states[0]->cities[0]->id;

        postJson('/api/production/project/deals', $requestData);

        // get current project deal data
        $currentDeal = \Modules\Production\Models\ProjectDeal::select('id')->latest()->first();
        $projectDealUid = \Illuminate\Support\Facades\Crypt::encryptString($currentDeal->id);

        $quotationId = str_replace('#', '', $requestData['quotation']['quotation_id']);
        $encrypted = \Illuminate\Support\Facades\Crypt::encryptString($quotationId);

        // mocking
        $client = Mockery::mock(GeneralService::class);
        $client->shouldReceive('uploadImageandCompress')
            ->withAnyArgs()
            ->andReturn('image.webp');

        $response = postJson('/api/finance/transaction/' . $encrypted . '/' . $projectDealUid, $payload);

        $response->assertStatus(201);
        expect($response->json())->toHaveKey('message');

        $currentDeal = ProjectQuotation::selectRaw('id,project_deal_id')
            ->where('quotation_id', $quotationId)
            ->first();
        $this->assertDatabaseHas('project_deals', [
            'id' => $currentDeal->project_deal_id,
            'is_fully_paid'  => 1
        ]);
    });

    it("Transaction Created Successfully", function() use ($requestData) {
        $payload = getPayload();

        // create deal
        $requestData = prepareProjectDeal($requestData);

        $country = Country::factory()
        ->has(
            State::factory()
                ->has(City::factory())
        )
        ->create();
        $requestData['country_id'] = $country->id;
        $requestData['state_id'] = $country->states[0]->id;
        $requestData['city_id'] = $country->states[0]->cities[0]->id;

        postJson('/api/production/project/deals', $requestData);

        $encrypted = \Illuminate\Support\Facades\Crypt::encryptString(str_replace('#', '', $requestData['quotation']['quotation_id']));

        // get current project deal data
        $currentDeal = \Modules\Production\Models\ProjectDeal::selectRaw('id,identifier_number')->latest()->first();
        $projectDealUid = \Illuminate\Support\Facades\Crypt::encryptString($currentDeal->id);

        // mocking
        $client = Mockery::mock(GeneralService::class);
        $client->shouldReceive('uploadImageandCompress')
            ->withAnyArgs()
            ->andReturn('image.webp');

        $response = postJson('/api/finance/transaction/' . $encrypted . '/' . $projectDealUid, $payload);

        $response->assertStatus(201);
        $this->assertDatabaseCount('transactions', 1);

        // get current transaction
        $currentTrx = \Modules\Finance\Models\Transaction::select('trx_id')->latest()->first();
        $this->assertStringContainsString($currentDeal->identifier_number, $currentTrx->trx_id);
    });
});
