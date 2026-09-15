<?php

use App\Actions\DefineTaskAction;
use App\Enums\Production\ProjectStatus;
use App\Enums\Production\TaskStatus;
use App\Enums\System\BaseRole;
use App\Models\User;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\ProjectTask;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * DefineTaskAction builds the per-task action buttons. Rule under test: the members button
 * (action 'choosePicAction') must never appear when the distribute button (action
 * 'distributeTaskAction') is shown for the same task/user.
 *
 * getDistributeTaskButton() shows the distribute button when a superpower/pic user views a
 * WaitingDistribute task, or when a lead modeller views a pool task.
 */
function dtaUserWithRole(string $role, array $permissions = []): User
{
    Role::firstOrCreate(['name' => $role, 'guard_name' => 'sanctum']);

    $employee = Employee::factory()->withUser()->create();
    $user = User::where('employee_id', $employee->id)->firstOrFail();
    $user->assignRole($role);

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']));
    }

    return $user->refresh();
}

/**
 * @param  array<int, array<string, mixed>>  $output
 * @return array<int, string>
 */
function dtaActions(array $output): array
{
    return collect($output)->pluck('action')->all();
}

describe('members button vs distribute button', function () {
    it('hides the members button when the distribute button is shown (superpower, WaitingDistribute)', function () {
        $user = dtaUserWithRole(BaseRole::Root->value);
        actingAs($user);

        $task = ProjectTask::factory()->create([
            'status' => TaskStatus::WaitingDistribute->value,
            'is_pool_task' => false,
        ]);

        $actions = dtaActions(DefineTaskAction::run($task, $user, ProjectStatus::OnGoing->value, 0, false));

        expect($actions)->toContain('distributeTaskAction')
            ->and($actions)->not->toContain('choosePicAction');
    });

    it('shows the members button when the distribute button is not shown (superpower, WaitingApproval)', function () {
        $user = dtaUserWithRole(BaseRole::Root->value);
        actingAs($user);

        $task = ProjectTask::factory()->create([
            'status' => TaskStatus::WaitingApproval->value,
            'is_pool_task' => false,
        ]);

        $actions = dtaActions(DefineTaskAction::run($task, $user, ProjectStatus::OnGoing->value, 0, false));

        expect($actions)->toContain('choosePicAction')
            ->and($actions)->not->toContain('distributeTaskAction');
    });

    it('hides the members button for a lead modeller on a pool task where distribute is shown', function () {
        $user = dtaUserWithRole(BaseRole::LeadModeller->value, ['create_pool_task']);
        actingAs($user);

        $task = ProjectTask::factory()->create([
            'status' => null,
            'is_pool_task' => true,
        ]);

        $actions = dtaActions(DefineTaskAction::run($task, $user, ProjectStatus::OnGoing->value, 0, false));

        expect($actions)->toContain('distributeTaskAction')
            ->and($actions)->not->toContain('choosePicAction');
    });
});
