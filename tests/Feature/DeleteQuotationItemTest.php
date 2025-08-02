<?php

use Modules\Production\Models\ProjectDeal;
use Modules\Production\Models\ProjectDealMarketing;
use Modules\Production\Models\ProjectQuotation;

it("Delete quotation item when already have quotation data", function () {
    $projectDeal = ProjectDeal::factory()
        ->has(ProjectDealMarketing::factory()->count(2), 'marketings')
        ->create();

    $quotations = ProjectQuotation::factory()
        ->for($projectDeal, 'deal')
        ->withItems(count: 0, name: 'Quotation Item 1')
        ->create();

    $quotationItem = $quotations->items->first();

    $service = createQuotationItemService();

    $response = $service->delete(id: $quotationItem->id);

    expect($response)->toHavekey('error');
    expect($response['error'])->toBeFalse();

    $this->assertDatabaseHas('quotation_items', [
        'name' => 'Quotation Item 1',
    ]);
});
