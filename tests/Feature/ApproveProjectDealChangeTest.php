<?php

use App\Enums\Production\ProjectDealChangeStatus;
use App\Enums\Production\ProjectDealStatus;
use App\Models\User;
use App\Services\GeneralService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Modules\Hrd\Models\Employee;
use Modules\Production\Jobs\NotifyApprovalProjectDealChangeJob;
use Modules\Production\Models\ProjectDeal;
use Modules\Production\Models\ProjectDealChange;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\getJson;

it('Approve changes return success', function () {
    Bus::fake();

    $permissions = [
        'approve_project_deal_change',
    ];
    $user = initAuthenticateUser(permissions: $permissions);

    $this->actingAs($user);

    $requested = Employee::factory()->create([
        'user_id' => User::factory(),
    ]);

    $change = ProjectDealChange::factory()
        ->create([
            'requested_by' => $requested->user_id,
        ]);

    $currentProjectDeal = ProjectDeal::find($change->project_deal_id);
    $currentName = $currentProjectDeal->name;

    $service = createProjectDealService();

    $response = $service->approveChangesProjectDeal(
        projectDetailChangesUid: Crypt::encryptString($change->id)
    );

    expect($response['error'])->toBeFalse();

    $this->assertDatabaseHas('project_deal_changes', [
        'id' => $change->id,
        'status' => ProjectDealChangeStatus::Approved->value,
    ]);

    $this->assertDatabaseMissing('project_deal_changes', [
        'id' => $change->id,
        'approval_at' => null,
        'approval_by' => null,
    ]);

    $this->assertDatabaseHas('project_deals', [
        'id' => $change->project_deal_id,
        'name' => $currentName.' Update',
        'status' => ProjectDealStatus::Final->value,
    ]);

    $this->assertDatabaseHas('projects', [
        'project_deal_id' => $change->project_deal_id,
        'name' => $currentName.' Update',
    ]);

    Bus::assertDispatched(NotifyApprovalProjectDealChangeJob::class);
});

it('Approve changes when user do not have permission', function () {
    Permission::create(['name' => 'approve_project_deal_change', 'guard_name' => 'sanctum']);

    $user = initAuthenticateUser();

    $this->actingAs($user);

    $requested = Employee::factory()->create([
        'user_id' => User::factory(),
    ]);

    $change = ProjectDealChange::factory()
        ->create([
            'requested_by' => $requested->user_id,
        ]);

    $currentProjectDeal = ProjectDeal::find($change->project_deal_id);
    $currentName = $currentProjectDeal->name;

    $service = createProjectDealService();

    $response = $service->approveChangesProjectDeal(
        projectDetailChangesUid: Crypt::encryptString($change->id)
    );

    expect($response['error'])->toBeTrue();
    expect($response['code'])->toBe(403);
});

it('Approve event that already approve before', function () {
    Bus::fake();

    $requested = Employee::factory()->create([
        'user_id' => User::factory(),
    ]);

    $change = ProjectDealChange::factory()
        ->create([
            'requested_by' => $requested->user_id,
            'status' => ProjectDealChangeStatus::Approved->value,
        ]);

    $service = createProjectDealService();

    $response = $service->approveChangesProjectDeal(projectDetailChangesUid: Crypt::encryptString($change->id));

    expect($response['error'])->toBeTrue();

    expect($response['message'])->toBe('Changes has already approved');

    Bus::assertNotDispatched(NotifyApprovalProjectDealChangeJob::class);
});

it('Approve changes from email', function () {
    Bus::fake();

    $requested = Employee::factory()->create([
        'user_id' => User::factory(),
    ]);

    $approvalEmployee = Employee::factory()
        ->withUser()
        ->create();

    $employee = Employee::with('user')->find($approvalEmployee->id);

    $change = ProjectDealChange::factory()
        ->create([
            'requested_by' => $requested->user_id,
        ]);

    $currentProjectDeal = ProjectDeal::find($change->project_deal_id);
    $currentName = $currentProjectDeal->name;

    $approvalUrl = (new GeneralService)
        ->generateApprovalUrlForProjectDealChanges(user: $employee->user, changeDeal: $change, type: 'approved');

    getJson($approvalUrl);

    $this->assertDatabaseHas('project_deal_changes', [
        'id' => $change->id,
        'status' => ProjectDealChangeStatus::Approved->value,
        'approval_by' => $employee->user->id,
    ]);

    $this->assertDatabaseMissing('project_deal_changes', [
        'id' => $change->id,
        'approval_at' => null,
        'approval_by' => null,
    ]);

    $this->assertDatabaseHas('project_deals', [
        'id' => $change->project_deal_id,
        'name' => $currentName.' Update',
        'status' => ProjectDealStatus::Final->value,
    ]);

    $this->assertDatabaseHas('projects', [
        'project_deal_id' => $change->project_deal_id,
        'name' => $currentName.' Update',
    ]);

    Bus::assertDispatched(NotifyApprovalProjectDealChangeJob::class);
});
