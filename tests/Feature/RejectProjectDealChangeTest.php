<?php

use App\Enums\Production\ProjectDealChangeStatus;
use App\Models\User;
use App\Services\GeneralService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Modules\Hrd\Models\Employee;
use Modules\Production\Jobs\NotifyApprovalProjectDealChangeJob;
use Modules\Production\Models\ProjectDeal;
use Modules\Production\Models\ProjectDealChange;

use function Pest\Laravel\getJson;

it('Reject changes return success', function () {
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

    $service = createProjectDealService();

    $response = $service->rejectChangesProjectDeal(
        projectDetailChangesUid: Crypt::encryptString($change->id),
        payload: []
    );

    expect($response['error'])->toBeFalse();

    $this->assertDatabaseHas('project_deal_changes', [
        'project_deal_id' => $change->project_deal_id,
        'status' => ProjectDealChangeStatus::Rejected->value,
    ]);

    $this->assertDatabaseMissing('project_deal_changes', [
        'project_deal_id' => $change->project_deal_id,
        'rejected_at' => null,
        'rejected_by' => null,
    ]);
    Bus::assertDispatched(NotifyApprovalProjectDealChangeJob::class);
});

it('Reject changes from email', function () {
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
        ->generateApprovalUrlForProjectDealChanges(user: $employee->user, changeDeal: $change, type: 'rejected');

    getJson($approvalUrl);

    $this->assertDatabaseHas('project_deal_changes', [
        'id' => $change->id,
        'status' => ProjectDealChangeStatus::Rejected->value,
        'rejected_by' => $employee->user->id,
    ]);

    $this->assertDatabaseMissing('project_deal_changes', [
        'id' => $change->id,
        'rejected_at' => null,
        'rejected_by' => null,
    ]);

    Bus::assertDispatched(NotifyApprovalProjectDealChangeJob::class);
});
