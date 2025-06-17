<?php

use App\Services\Geocoding;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Modules\Company\Models\Country;
use Modules\Company\Models\ProjectClass;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\Customer;
use Modules\Production\Models\ProjectDeal;
use Modules\Production\Models\QuotationItem;

function createDeal($customer, $projectClass, $employee, $quotationItem) {
    $payload = getProjectDealPayload($customer, $projectClass, $employee, $quotationItem);
    $projectService = createProjectService();

    $response = $projectService->storeProjectDeals($payload);

    return ProjectDeal::where('name', $payload['name'])
        ->where('project_date', $payload['project_date'])
        ->first();
}

// define datasets
dataset('dataDeals', [
    fn() => [
        Customer::factory()->create(),
        ProjectClass::factory()->create(),
        Employee::factory()->create(),
        QuotationItem::factory()->create(),
    ]
]);

describe('Publish Quotation', function () {
    it("Publish quotation as final return success", function (Customer $customer, ProjectClass $projectClass, Employee $employee, QuotationItem $quotationItem) {
        $currentDeal = createDeal($customer, $projectClass, $employee, $quotationItem);
        $service = createProjectDealService();

        // mock geocoding
        $geo = Mockery::mock(Geocoding::class);
        $geo->shouldReceive('getCoordinate')
            ->withAnyArgs()
            ->andReturn([
                'longitude' => fake()->longitude(),
                'latitude' => fake()->latitude(),
            ]);

        $response = $service->publishProjectDeal(
            projectDealId: Crypt::encryptString($currentDeal->id),
            type: 'publish_final'
        );

        logging("RESPONSE FINAL", $response);

        expect($response['error'])->toBeFalse();

        $this->assertDatabaseHas('project_deals', [
            'id' => $currentDeal->id,
            'status' => \App\Enums\Production\ProjectDealStatus::Final->value,
        ]);
        $this->assertDatabaseHas('project_quotations', [
            'is_final' => 1,
            'project_deal_id' => $currentDeal->id,
        ]);
        $this->assertDatabaseHas('projects', [
            'name' => $currentDeal->name
        ]);
    })->with('dataDeals');

    it("Publish quotation as temporary return success", function (Customer $customer, ProjectClass $projectClass, Employee $employee, QuotationItem $quotationItem) {
        $currentDeal = createDeal($customer, $projectClass, $employee, $quotationItem);
        $service = createProjectDealService();

        $response = $service->publishProjectDeal(
            projectDealId: Crypt::encryptString($currentDeal->id),
            type: 'publish'
        );

        expect($response['error'])->toBeFalse();

        $this->assertDatabaseHas('project_deals', [
            'id' => $currentDeal->id,
            'status' => \App\Enums\Production\ProjectDealStatus::Temporary->value,
        ]);
    })->with('dataDeals');
});
