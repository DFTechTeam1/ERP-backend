<?php

use App\Enums\Production\ProjectDealStatus;
use Modules\Company\Models\ProjectClass;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\Customer;
use Modules\Production\Models\ProjectDeal;
use Modules\Production\Models\QuotationItem;

use function Pest\Laravel\{getJson, postJson, withHeaders, actingAs};

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});

describe('Create Project Deal', function () {

    it('Create Project Return Failed', function () {
        $response = postJson('/api/production/project/deals', []);
        $response->assertStatus(422);

        expect($response->json())->toHaveKey('errors');
    });

    it("Create Deals Return Success", function(Customer $customer) {
        $requestData = getProjectDealPayload($customer);
        $requestData = prepareProjectDeal($requestData);

        // change status
        $requestData['status'] = ProjectDealStatus::Draft->value;

        // change name
        $requestData['name'] = 'Draft project';

        $response = postJson('/api/production/project/deals', $requestData);

        $response->assertStatus(201);
        $this->assertDatabaseCount('project_deals', 1);
        $this->assertDatabaseHas('project_deals', [
            'name' => 'Draft project',
            'identifier_number' => '0951'
        ]);
        $this->assertDatabaseMissing('projects', [
            'name' => 'Draft project',
        ]);
        $this->assertDatabaseCount('project_quotations', 1);
        $this->assertDatabaseCount('project_deal_marketings', 1);
        $this->assertDatabaseCount('transactions', 0);
    })->with([
        fn() => Customer::factory()->create()
    ]);

    it('Create final project deal directly', function (Customer $customer) {
        $requestData = getProjectDealPayload($customer);
        $requestData = prepareProjectDeal($requestData);

        // set to final
        $requestData['status'] = ProjectDealStatus::Final->value;
        
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
            'name' => 'Final Project',
            'identifier_number' => '0951'
        ]);
        $this->assertDatabaseHas('projects', [
            'name' => 'Final Project'
        ]);
    })->with([
        fn() => Customer::factory()->create()
    ]);
});
