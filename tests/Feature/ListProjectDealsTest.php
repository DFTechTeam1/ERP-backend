<?php

use App\Enums\Production\ProjectDealStatus;
use Modules\Finance\Models\ProjectDealPriceChange;
use Modules\Production\Models\ProjectDeal;

use function Pest\Laravel\getJson;

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});

function projectDealListStructure()
{
    return [
        'uid',
        'latest_quotation_id',
        'name',
        'venue',
        'project_date',
        'city',
        'collaboration',
        'marketing',
        'down_payment',
        'remaining_payment',
        'remaining_payment_raw',
        'status_project',
        'status_project_color',
        'fix_price',
        'latest_price',
        'is_fully_paid',
        'status_payment',
        'status_payment_color',
        'can_make_payment',
        'can_publish_project',
        'can_make_final',
        'can_edit',
        'can_delete',
        'can_cancel',
        'can_approve_event_changes',
        'can_reject_event_changes',
        'is_final',
        'quotation' => [
            'id',
            'fix_price',
        ],
        'unpaidInvoices',
        'can_request_price_changes',
        'have_request_changes',
        'have_price_changes',
        'can_approve_price_changes',
        'can_reject_price_changes',
        'changes_id',
        'price_changes_id',
    ];
}

it('Should return the correct number of project deals', function () {
    ProjectDeal::factory()
        ->withQuotation()
        ->count(4)
        ->create();

    $data = getJson(route('api.production.project-deal.list').'?itemsPerPage=10&page=1');

    $data->assertStatus(201);

    $data->assertJsonStructure([
        'message',
        'data' => [
            'paginated' => [
                '*' => projectDealListStructure(),
            ],
            'totalData',
        ],
    ]);

    expect($data->json()['data']['totalData'])->toBe(4);
});

it('List deal with filter', function () {
    ProjectDeal::factory()
        ->withQuotation()
        ->count(4)
        ->create([
            'status' => ProjectDealStatus::Temporary->value,
        ]);

    ProjectDeal::factory()
        ->withQuotation()
        ->create([
            'name' => 'custom name',
            'status' => ProjectDealStatus::Final->value,
        ]);

    $response = getJson(route('api.production.project-deal.list').'?itemPerPage=10&page=1&status[0][id]='.ProjectDealStatus::Final->value.'&status[0][name]='.ProjectDealStatus::Final->label());

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'message',
        'data' => [
            'paginated' => [
                '*' => projectDealListStructure(),
            ],
            'totalData',
        ],
    ]);

    expect($response->json()['data']['totalData'])->toBe(1);
});

it('List deal when have price request changes', function () {
    ProjectDeal::factory()
        ->withQuotation()
        ->count(4)
        ->create([
            'status' => ProjectDealStatus::Temporary->value,
        ]);

    $project = ProjectDeal::factory()
        ->withQuotation()
        ->create([
            'name' => 'custom name',
            'status' => ProjectDealStatus::Final->value,
        ]);

    ProjectDealPriceChange::factory()->create([
        'project_deal_id' => $project->id,
    ]);

    $response = getJson(route('api.production.project-deal.list').'?itemPerPage=10&page=1&status[0][id]='.ProjectDealStatus::Final->value.'&status[0][name]='.ProjectDealStatus::Final->label());

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'message',
        'data' => [
            'paginated' => [
                '*' => projectDealListStructure(),
            ],
            'totalData',
        ],
    ]);

    expect($response->json()['data']['totalData'])->toBe(1);
    expect($response->json()['data']['paginated'][0]['price_changes_id'])->toBeString();
    expect($response->json()['data']['paginated'][0]['status_project'])->toBe(__('notification.waitingForApproval'));
});
