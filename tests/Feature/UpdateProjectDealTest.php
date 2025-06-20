<?php

use Illuminate\Support\Facades\Crypt;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\Customer;
use Modules\Production\Models\ProjectDeal;
use Modules\Production\Models\ProjectDealMarketing;
use Modules\Production\Models\ProjectQuotation;

it('Update project deal with same marketing data', function (Customer $customer) {
    $projectDeal = ProjectDeal::factory()
        ->create([
            'name' => "First project"
        ]);

    $marketings = ProjectDealMarketing::factory()
        ->for($projectDeal)
        ->count(2)
        ->create();

    ProjectQuotation::factory()
        ->for($projectDeal, 'deal')
        ->withItems()
        ->create();

    // prepare data
    $requestData = getProjectDealPayload($customer);
    $requestData = prepareProjectDeal($requestData);

    $requestData['name'] = 'Update project';

    // setup marketing
    $marketingIds = [];
    foreach ($marketings as $marketing) {
        $employee = Employee::select('uid')
            ->find($marketing->employee_id);
        $marketingIds[] = $employee->uid;
    }
    $requestData['marketing_id'] = $marketingIds;

    $service = createProjectService();
    $response = $service->updateProjectDeals(payload: $requestData, projectDealUid: Crypt::encryptString($projectDeal->id));

    expect($response)->toHaveKey('error');
    expect($response['error'])->toBeFalse();

    $this->assertDatabaseHas('project_deals', [
        'name' => 'Update project'
    ]);
    $this->assertDatabaseMissing('project_deals', [
        'name' => 'First project'
    ]);
    $this->assertDatabaseCount('project_deal_marketings', 2);

})->with([
    fn() => Customer::factory()->create()
]);

it('Update project with different marketing', function(Customer $customer) {
    $projectDeal = ProjectDeal::factory()
        ->create([
            'name' => "First project"
        ]);

    $marketings = ProjectDealMarketing::factory()
        ->for($projectDeal)
        ->count(2)
        ->create();

    ProjectQuotation::factory()
        ->for($projectDeal, 'deal')
        ->withItems()
        ->create();

    // prepare data
    $requestData = getProjectDealPayload($customer);
    $requestData = prepareProjectDeal($requestData);

    $requestData['name'] = 'Update project';

    $service = createProjectService();
    $response = $service->updateProjectDeals(payload: $requestData, projectDealUid: Crypt::encryptString($projectDeal->id));

    expect($response)->toHaveKey('error');
    expect($response['error'])->toBeFalse();

    $this->assertDatabaseHas('project_deals', [
        'name' => 'Update project'
    ]);
    $this->assertDatabaseMissing('project_deals', [
        'name' => 'First project'
    ]);
    $this->assertDatabaseCount('project_deal_marketings', 1);
})->with([
    fn() => Customer::factory()->create()
]);
