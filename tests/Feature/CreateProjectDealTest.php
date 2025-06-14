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

describe('Create Project Deal', function () {

    it('Create Project Return Failed', function () {
        $response = postJson('/api/production/project/deals', []);
        $response->assertStatus(422);

        expect($response->json())->toHaveKey('errors');
    });

    it("Create Deals Return Success", function(Customer $customer) {
        $requestData = getProjectDealPayload($customer);
        $requestData = prepareProjectDeal($requestData);

        $response = postJson('/api/production/project/deals', $requestData);

        $response->assertStatus(201);
        $this->assertDatabaseCount('project_deals', 1);
        $this->assertDatabaseCount('project_quotations', 1);
        $this->assertDatabaseCount('transactions', 0);
    })->with([
        fn() => Customer::factory()->create()
    ]);
});
