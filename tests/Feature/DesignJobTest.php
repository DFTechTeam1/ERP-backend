<?php

use App\Enums\Cache\CacheKey;
use App\Enums\Production\ProjectDealStatus;
use Illuminate\Support\Facades\Bus;
use Modules\Finance\Jobs\ProjectHasBeenFinal;
use Modules\Production\Models\Customer;

describe('Project count will be update when', function () {
    it('New project is created', function () {
        $generalService = new \App\Services\GeneralService();
        $initialCount = $generalService->getCache(CacheKey::ProjectCount->value) ?? 0;

        // Simulate project creation
        \Modules\Production\Models\Project::factory()->create();

        $updatedCount = $generalService->getCache(CacheKey::ProjectCount->value);
        expect($updatedCount)->toBe($initialCount + 2);
    });

    it('Project is deleted', function () {
        $generalService = new \App\Services\GeneralService();
        $initialCount = $generalService->getCache(CacheKey::ProjectCount->value) ?? 0;

        // Simulate project creation
        $project = \Modules\Production\Models\Project::factory()->create();

        // Simulate project deletion
        $project->delete();

        $updatedCount = $generalService->getCache(CacheKey::ProjectCount->value);
        expect($updatedCount)->toBe($initialCount + 1);
    });

    it("Project deal has been created", function (Customer $customer) {
        Bus::fake();

        $requestData = getProjectDealPayload($customer);
        $requestData = prepareProjectDeal($requestData);

        // set to final
        $requestData['status'] = ProjectDealStatus::Final->value;
        $requestData['quotation']['is_final'] = 1;
        
        // change name
        $requestData['name'] = 'Final Project';
        
        // modify quotation id
        $requestData['quotation']['quotation_id'] = 'DF0010';

        $service = createProjectService();

        $response = $service->storeProjectDeals(payload: $requestData);

        expect($response)->toHaveKey('error');
        expect($response['error'])->toBeFalse();
        expect($response['data'])->toHaveKey('url');

        $this->assertDatabaseHas('project_deals', [
            'name' => 'Final Project'
        ]);
        $this->assertDatabaseHas('projects', [
            'name' => 'Final Project'
        ]);

        $generalService = new \App\Services\GeneralService();
        $updatedCount = $generalService->getCache(CacheKey::ProjectCount->value);
        expect($updatedCount)->toBe(2);

        Bus::assertDispatched(ProjectHasBeenFinal::class);
    })->with([
        fn() => Customer::factory()->create()
    ]);
});
