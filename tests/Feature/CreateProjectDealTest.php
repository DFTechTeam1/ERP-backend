<?php

use Modules\Company\Models\ProjectClass;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\Customer;
use Modules\Production\Models\QuotationItem;

use function Pest\Laravel\{getJson, postJson, withHeaders, actingAs};

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
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
        'quotation_id' => '#DF04022',
        'is_final' => 0,
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
        'description' => ''
    ],
    'request_type' => 'save_and_download' // will be draft,save,save_and_download
];

describe('Create Project Deal', function () use ($requestData) {

    it('Create Project Return Failed', function () {
        $response = postJson('/api/production/project/deals', []);
        $response->assertStatus(422);

        expect($response->json())->toHaveKey('errors');
    });

    it("Create Deals Return Success", function() use ($requestData) {
        $requestData = prepareProjectDeal($requestData);

        $response = postJson('/api/production/project/deals', $requestData);

        $response->assertStatus(201);
        $this->assertDatabaseCount('project_deals', 1);
        $this->assertDatabaseCount('project_quotations', 1);
    });
});
