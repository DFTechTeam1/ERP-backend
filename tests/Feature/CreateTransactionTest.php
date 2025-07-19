<?php

use App\Enums\Production\ProjectDealStatus;
use App\Services\GeneralService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Modules\Company\Models\City;
use Modules\Company\Models\Country;
use Modules\Company\Models\State;
use Modules\Finance\Jobs\TransactionCreatedJob;
use Modules\Finance\Models\Invoice;
use Modules\Production\Models\ProjectDeal;
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
    it('Create Transaction On Current Invoice', function () use ($requestData) {
        Bus::fake();

        $country = Country::factory()
            ->has(
                State::factory()->has(
                    City::factory()
                )
            )->create();

        $projectDeal = ProjectDeal::factory()
            ->has(ProjectQuotation::factory()->state([
                'is_final' => 1
            ]), 'quotations')
            ->has(Invoice::factory()->state([
                'status' => \App\Enums\Transaction\InvoiceStatus::Unpaid->value,
                'parent_number' => 'VI/2025 951',
                'number' => 'VI/2025 951 A',
                'sequence' => 1,
                'amount' => 10000000,
                'raw_data' => [
                    'transactions' => []
                ]
            ]))
            ->create([
                'country_id' => $country->id,
                'state_id' => $country->states[0]->id,
                'state_id' => $country->states[0]->cities[0]->id,
                'status' => ProjectDealStatus::Final->value
            ]);

        $service = setTransactionService();

        $trxDate = now()->addDays(3)->format('Y-m-d');

        $file = UploadedFile::fake()->image('testing.jpg');
        $payload = [
            'payment_amount' => 10000000,
            'transaction_date' => $trxDate,
            'invoice_id' => \Illuminate\Support\Facades\Crypt::encryptString($projectDeal->invoices[0]->id),
            'note' => '',
            'reference' => '',
            'images' => [
                [
                    'image' => $file
                ]
            ]
        ];

        $response = $service->store(payload: $payload, projectDealUid: \Illuminate\Support\Facades\Crypt::encryptString($projectDeal->id));
        logging("CREATE TRANSACTION TEST", $response);
        expect($response)->toHaveKeys(['error', 'message']);
        expect($response['error'])->toBeFalse();

        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('transactions', [
            'invoice_id' => $projectDeal->invoices[0]->id,
        ]);
        $this->assertDatabaseHas('invoices', [
            'id' => $projectDeal->invoices[0]->id,
            'status' => \App\Enums\Transaction\InvoiceStatus::Paid->value
        ]);

        Bus::assertDispatched(TransactionCreatedJob::class);
    });
});
