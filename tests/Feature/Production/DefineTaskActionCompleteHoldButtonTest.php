<?php

use App\Actions\DefineTaskAction;
use App\Enums\Production\ProjectStatus;
use App\Enums\Production\TaskPicStatus;
use App\Enums\Production\TaskStatus;
use App\Enums\System\BaseRole;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\ProjectTask;
use Modules\Production\Models\ProjectTaskPic;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * Rule: the lead modeller must not see the complete ('completeTask') or hold ('holdTaskAction')
 * buttons on an in-progress task their team is working on when they are not the task's PIC.
 * The lead modeller is the acting user whose employee matches the 'lead_3d_modeller' setting.
 *
 * The buttons otherwise show for a superpower/owner user on OnProgress/Revise tasks, which is why
 * the tests give the acting user superpower (isProjectPic) to prove the guard actually suppresses
 * a button that would otherwise appear.
 */
function chUserWithRole(string $role): User
{
    Role::firstOrCreate(['name' => $role, 'guard_name' => 'sanctum']);

    $employee = Employee::factory()->withUser()->create();
    $user = User::where('employee_id', $employee->id)->firstOrFail();
    $user->assignRole($role);
    actingAs($user);

    return $user->refresh();
}

function chMakeLeadModeller(User $user): void
{
    Cache::forever('setting', [
        ['key' => 'lead_3d_modeller', 'value' => Employee::find($user->employee_id)->uid],
    ]);
}

/**
 * @return array<int, string>
 */
function chActions(ProjectTask $task, User $user, bool $isProjectPic = true): array
{
    return collect(DefineTaskAction::run($task, $user, ProjectStatus::OnGoing->value, 0, $isProjectPic))
        ->pluck('action')->all();
}

function chTask(?int $status, ?Employee $pic = null): ProjectTask
{
    $task = ProjectTask::factory()->create(['status' => $status]);
    if ($pic) {
        ProjectTaskPic::create([
            'project_task_id' => $task->id,
            'employee_id' => $pic->id,
            'status' => TaskPicStatus::Approved->value,
        ]);
    }

    return $task;
}

describe('complete/hold buttons for the lead modeller', function () {
    it('hides complete and hold when the lead modeller is not the PIC of an in-progress task', function () {
        $lead = chUserWithRole(BaseRole::LeadModeller->value);
        chMakeLeadModeller($lead);

        // a team member (not the lead modeller) owns the in-progress task
        $task = chTask(TaskStatus::OnProgress->value, Employee::factory()->create());

        $actions = chActions($task, $lead); // isProjectPic: true -> would otherwise have superpower

        expect($actions)->not->toContain('completeTask')
            ->and($actions)->not->toContain('holdTaskAction');
    });

    it('still shows complete and hold when the lead modeller is the PIC', function () {
        $lead = chUserWithRole(BaseRole::LeadModeller->value);
        chMakeLeadModeller($lead);

        $task = chTask(TaskStatus::OnProgress->value, Employee::find($lead->employee_id));

        $actions = chActions($task, $lead);

        expect($actions)->toContain('completeTask')
            ->and($actions)->toContain('holdTaskAction');
    });

    it('does not hide for a non-lead-modeller superpower user who is not the PIC', function () {
        $director = chUserWithRole(BaseRole::Director->value);

        $task = chTask(TaskStatus::OnProgress->value, Employee::factory()->create());

        $actions = chActions($task, $director);

        expect($actions)->toContain('completeTask')
            ->and($actions)->toContain('holdTaskAction');
    });

    it('only suppresses on in-progress, not on revise', function () {
        $lead = chUserWithRole(BaseRole::LeadModeller->value);
        chMakeLeadModeller($lead);

        $task = chTask(TaskStatus::Revise->value, Employee::factory()->create());

        $actions = chActions($task, $lead);

        // the rule targets in-progress; a revise task keeps the buttons for a superpower user
        expect($actions)->toContain('completeTask')
            ->and($actions)->toContain('holdTaskAction');
    });
});
