<?php

use App\Actions\DefineTaskAction;
use App\Enums\Production\ProjectStatus;
use App\Enums\Production\TaskPicStatus;
use App\Enums\Production\TaskStatus;
use App\Enums\System\BaseRole;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Modules\Company\Models\PositionBackup;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\ProjectTask;
use Modules\Production\Models\ProjectTaskPic;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * The revert-to-distribute button (action 'revertDistributeAction') must only appear on a
 * WaitingApproval task whose current PIC belongs to the 3D modeller team (position matches the
 * 'special_production_position' setting), and only for a user in an allowed role. Resolving the
 * modeller team mirrors getProject3DMember() in ProjectService.
 */
function rdbActingUser(string $role): User
{
    Role::firstOrCreate(['name' => $role, 'guard_name' => 'sanctum']);

    $employee = Employee::factory()->withUser()->create();
    $user = User::where('employee_id', $employee->id)->firstOrFail();
    $user->assignRole($role);
    actingAs($user);

    return $user->refresh();
}

function rdbTaskWithPic(Employee $pic): ProjectTask
{
    $task = ProjectTask::factory()->create(['status' => TaskStatus::WaitingApproval->value]);
    ProjectTaskPic::create([
        'project_task_id' => $task->id,
        'employee_id' => $pic->id,
        'status' => TaskPicStatus::Approved->value,
    ]);

    return $task;
}

/**
 * @return array<int, string>
 */
function rdbActions(ProjectTask $task, User $user): array
{
    return collect(DefineTaskAction::run($task, $user, ProjectStatus::OnGoing->value, 0, false))
        ->pluck('action')->all();
}

beforeEach(function () {
    // The modeller team = employees whose position matches the special_production_position setting.
    $this->modellerPosition = PositionBackup::factory()->create();
    Cache::forever('setting', [
        ['key' => 'special_production_position', 'value' => $this->modellerPosition->uid],
    ]);
});

describe('revert distribute button', function () {
    it('shows for a WaitingApproval task whose PIC is a 3D modeller', function () {
        $user = rdbActingUser(BaseRole::ProjectManager->value);
        $modeller = Employee::factory()->create(['position_id' => $this->modellerPosition->id]);

        $actions = rdbActions(rdbTaskWithPic($modeller), $user);

        expect($actions)->toContain('revertDistributeAction');
    });

    it('does not show when the current PIC is not a 3D modeller', function () {
        $user = rdbActingUser(BaseRole::ProjectManager->value);
        $regular = Employee::factory()->create(); // its own, non-modeller position

        $actions = rdbActions(rdbTaskWithPic($regular), $user);

        expect($actions)->not->toContain('revertDistributeAction');
    });

    it('does not show when the task has no PIC', function () {
        $user = rdbActingUser(BaseRole::ProjectManager->value);
        $task = ProjectTask::factory()->create(['status' => TaskStatus::WaitingApproval->value]);

        $actions = rdbActions($task, $user);

        expect($actions)->not->toContain('revertDistributeAction');
    });

    it('does not show for a disallowed role even with a modeller PIC', function () {
        $user = rdbActingUser(BaseRole::Marketing->value);
        $modeller = Employee::factory()->create(['position_id' => $this->modellerPosition->id]);

        $actions = rdbActions(rdbTaskWithPic($modeller), $user);

        expect($actions)->not->toContain('revertDistributeAction');
    });
});
