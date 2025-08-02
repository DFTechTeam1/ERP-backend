<?php

use Modules\Production\Models\ProjectDeal;
use Modules\Production\Models\ProjectDealMarketing;
use Modules\Production\Models\QuotationItem;

it('Add more quotation return success', function () {
    // create project first
    $projectDeal = ProjectDeal::factory()
        ->has(ProjectDealMarketing::factory()->count(2), 'marketings')
        ->create([
            'name' => 'New Deal'
        ]);

    $quuotationItem = QuotationItem::factory()->create();

    $payload = [
        'quotation' => [
            'quotation_id' => 'DF04022',
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
            'items' => [
                $quuotationItem->id
            ],
            'description' => '',
            'design_job' => 1
        ],
    ];

    $service = createProjectDealService();

    $response = $service->addMoreQuotation(payload: $payload, projectDealUid: \Illuminate\Support\Facades\Crypt::encryptString($projectDeal->id));

    expect($response)->toHaveKey('error');
    expect($response['error'])->toBeFalse();
    
    $this->assertDatabaseHas('project_deals', [
        'name' => $projectDeal->name
    ]);
    $this->assertDatabaseCount('project_quotations', 1);
    $this->assertDatabaseHas('project_quotations', [
        'project_deal_id' => $projectDeal->id
    ]);
});
