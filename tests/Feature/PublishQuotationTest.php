<?php

use App\Services\GeneralService;
use App\Services\Geocoding;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Modules\Company\Models\Country;
use Modules\Company\Models\ProjectClass;
use Modules\Finance\Jobs\ProjectHasBeenFinal;
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
        Bus::fake();

        $currentDeal = createDeal($customer, $projectClass, $employee, $quotationItem);

        // mock geocoding
        $geo = Mockery::mock(Geocoding::class);
        $geo->shouldReceive('getCoordinate')
            ->withAnyArgs()
            ->andReturn([
                'longitude' => fake()->longitude(),
                'latitude' => fake()->latitude(),
            ]);

        $generalService = Mockery::mock(GeneralService::class);
        $generalService->shouldReceive('getSettingByKey')
            ->withAnyArgs()
            ->andReturn('[{"name":"Asset 3D","sort":0,"id":1},{"name":"Compositing","sort":1,"id":2},{"name":"Animating","sort":2,"id":3},{"name":"Finalize","sort":3,"id":4}]');

        $generalService->shouldReceive('linkShortener')
            ->withAnyArgs()
            ->andReturn('google');

        $service = createProjectDealService(
            generalService: $generalService
        );

        $response = $service->publishProjectDeal(
            projectDealId: Crypt::encryptString($currentDeal->id),
            type: 'publish_final'
        );

        expect($response['error'])->toBeFalse();

        expect($response)->toHaveKey('data');

        expect($response['data'])->toHaveKey('project');

        $this->assertDatabaseHas('project_deals', [
            'id' => $currentDeal->id,
            'status' => \App\Enums\Production\ProjectDealStatus::Final->value,
        ]);
        $this->assertDatabaseHas('project_quotations', [
            'is_final' => 1,
            'project_deal_id' => $currentDeal->id,
        ]);
        $this->assertDatabaseHas('projects', [
            'name' => $currentDeal->name,
            'project_deal_id' => $currentDeal->id
        ]);

        // check marketing
        $this->assertDatabaseHas('project_marketings', [
            'project_id' => $response['data']['project']['id']
        ]);

        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseHas('invoices', [
            'is_main' => 1,
            'parent_number' => null,
            'sequence' => 0,
            'status' => \App\Enums\Transaction\InvoiceStatus::Unpaid->value,
            'paid_amount' => 0
        ]);
        $this->assertDatabaseHas('project_boards', [
            'project_id' => $response['data']['project']['id']
        ]);

        Bus::assertDispatched(ProjectHasBeenFinal::class);
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
