<?php

use Modules\Production\Models\ProjectDeal;
use Modules\Production\Models\ProjectDealMarketing;
use Modules\Production\Models\ProjectQuotation;

it('Delete project deal return success', function () {
    $projectDeal = ProjectDeal::factory()
        ->has(ProjectDealMarketing::factory()->count(2), 'marketings')
        ->create();

    $quotations = ProjectQuotation::factory()
        ->for($projectDeal, 'deal')
        ->withItems()
        ->create();

    $service = createProjectDealService();

    $response = $service->delete(id: \Illuminate\Support\Facades\Crypt::encryptString($projectDeal->id));

    expect($response)->toHavekey('error');

    expect($response['error'])->toBeFalse();

    $this->assertDatabaseCount('project_quotation_items', 0);
    $this->assertDatabaseCount('project_quotations', 0);
    $this->assertDatabaseCount('project_marketings', 0);

    // check softdeletes
    $this->assertNotNull(
        ProjectDeal::withTrashed()->find($projectDeal->id)->deleted_at
    );
});

it('Delete final project deals', function () {
    $projectDeal = ProjectDeal::factory()
        ->has(ProjectDealMarketing::factory()->count(2), 'marketings')
        ->create([
            'status' => \App\Enums\Production\ProjectDealStatus::Final->value,
        ]);

    $service = createProjectDealService();

    $response = $service->delete(id: \Illuminate\Support\Facades\Crypt::encryptString($projectDeal->id));

    expect($response)->toHaveKey('error');

    expect($response['error'])->toBeTrue();
});
