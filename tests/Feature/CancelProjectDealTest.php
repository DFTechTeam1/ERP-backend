<?php

use App\Enums\Production\ProjectDealStatus;
use App\Enums\Production\ProjectLeadStatus;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Modules\Hrd\Models\Employee;
use Modules\Production\Jobs\ProjectDealCanceledJob;
use Modules\Production\Models\ProjectDeal;
use Modules\Production\Models\ProjectLead;

use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->user = initAuthenticateUser();

    $this->actingAs($this->user);
});

it('Cannot cancel project deal when status is final', function () {
    $project = ProjectDeal::factory()
        ->create([
            'status' => ProjectDealStatus::Final->value,
        ]);

    $response = postJson(route('api.production.project-deal.cancel', ['projectDealUid' => Crypt::encryptString($project->id)]), [
        'reason' => 'Batal',
    ]);
    $response->assertStatus(400);

    expect($response->json())->toHaveKeys(['message', 'data']);

    expect($response->json()['message'])->toEqual(__('notification.finalEventCannotBeCancel'));

    $this->assertDatabaseHas('project_deals', [
        'id' => $project->id,
        'status' => ProjectDealStatus::Final->value,
        'cancel_at' => null,
        'cancel_by' => null,
    ]);
});

it('Cancel project deal return success', function () {
    Bus::fake();

    $project = ProjectDeal::factory()
        ->create([
            'status' => ProjectDealStatus::Temporary->value,
        ]);

    $response = postJson(route('api.production.project-deal.cancel', ['projectDealUid' => Crypt::encryptString($project->id)]), [
        'reason' => 'Batal',
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('project_deals', [
        'id' => $project->id,
        'status' => ProjectDealStatus::Canceled->value,
        'cancel_reason' => 'Batal',
        'cancel_by' => $this->user->id,
    ]);

    Bus::assertDispatched(ProjectDealCanceledJob::class);
});

it('Cancel a draft project deal return success', function () {
    Bus::fake();

    $project = ProjectDeal::factory()
        ->create([
            'status' => ProjectDealStatus::Draft->value,
        ]);

    $response = postJson(route('api.production.project-deal.cancel', ['projectDealUid' => Crypt::encryptString($project->id)]), [
        'reason' => 'Batal',
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('project_deals', [
        'id' => $project->id,
        'status' => ProjectDealStatus::Canceled->value,
    ]);
});

it('Cancel project deal also cancels the linked project lead', function () {
    Bus::fake();

    $employee = Employee::factory()->create();

    $project = ProjectDeal::factory()
        ->create([
            'status' => ProjectDealStatus::Temporary->value,
        ]);

    $lead = ProjectLead::create([
        'name' => 'Lead',
        'project_date' => now()->toDateString(),
        'created_by' => $employee->id,
        'project_deal_id' => $project->id,
        'status' => ProjectLeadStatus::ACTIVE,
    ]);

    $response = postJson(route('api.production.project-deal.cancel', ['projectDealUid' => Crypt::encryptString($project->id)]), [
        'reason' => 'Batal',
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('project_leads', [
        'id' => $lead->id,
        'status' => ProjectLeadStatus::CANCELLED->value,
        'cancel_reason' => 'Batal',
        'cancel_by' => $this->user->id,
    ]);
});
