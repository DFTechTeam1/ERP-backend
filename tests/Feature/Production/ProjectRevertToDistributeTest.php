<?php

use App\Actions\Project\DetailCache;
use App\Actions\Project\FormatBoards;
use App\Enums\Production\TaskPicStatus;
use App\Enums\Production\TaskStatus;
use App\Enums\System\BaseRole;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectTask;
use Modules\Production\Models\ProjectTaskPic;
use Modules\Production\Models\ProjectTaskPicHistory;
use Modules\Production\Services\ProjectService;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

/**
 * revertToDistribute() rewinds a task that is WaitingApproval back to WaitingDistribute
 * (handing it back to the 3D lead modeller to re-distribute).
 *
 * Guards, in order:
 *   - project must exist (by uid)                -> "Project is not found"
 *   - task must exist AND belong to the project  -> "Task is not found"
 *   - task status must be WaitingApproval        -> CannotRewindStatusWhenTaskActive
 * On success the task status becomes WaitingDistribute, the current task pics are detached
 * (removed from project_task_pics and project_task_pic_histories), the configured 3D lead
 * modeller is assigned as the sole pic (status WaitingToDistribute), and the detail cache is
 * force-rebuilt. Every failure returns error=true / HTTP 400 and leaves the task untouched.
 * The lead modeller comes from the 'lead_3d_modeller' setting, seeded via the 'setting' cache.
 *
 * The detail pipeline (FormatBoards / DetailCache) is Lorisleiva-action based and resolved
 * from the container, so it is stubbed here; it is orthogonal to the status rewind.
 *
 * Route: GET /api/production/project/{projectUid}/task/{taskUid}/revertToDistribute, guarded by
 * auth.session plus role:root|director|lead modeller|project manager|project manager admin
 * (holding any one of those roles grants access).
 */
function revertService(): ProjectService
{
    return app(ProjectService::class);
}

function revertUrl(string $projectUid, string $taskUid): string
{
    return "/api/production/project/{$projectUid}/task/{$taskUid}/revertToDistribute";
}

/**
 * Roles the route middleware permits; holding any one grants access.
 *
 * @return array<string, array{string}>
 */
function revertPermittedRoles(): array
{
    return [
        'root' => [BaseRole::Root->value],
        'director' => [BaseRole::Director->value],
        'lead modeller' => [BaseRole::LeadModeller->value],
        'project manager' => [BaseRole::ProjectManager->value],
        'project manager admin' => [BaseRole::ProjectManagerAdmin->value],
    ];
}

/** Authenticate on the sanctum guard as a fresh user holding the given role. */
function revertActingAsRole(string $roleName): User
{
    Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'sanctum']);
    $user = User::factory()->create();
    $user->assignRole($roleName);
    actingAs($user, 'sanctum');

    return $user;
}

/**
 * Create a WaitingApproval task with $count pics, each backed by a real employee plus a
 * project_task_pics row and a project_task_pic_histories row.
 *
 * @return array{0: ProjectTask, 1: array<int, int>} the task and its pic employee ids
 */
function revertTaskWithPics(int $count = 2): array
{
    $task = ProjectTask::factory()->create(['status' => TaskStatus::WaitingApproval->value]);

    $employeeIds = [];
    for ($i = 0; $i < $count; $i++) {
        $employee = Employee::factory()->create();
        ProjectTaskPic::create([
            'project_task_id' => $task->id,
            'employee_id' => $employee->id,
            'status' => TaskPicStatus::Approved->value,
        ]);
        ProjectTaskPicHistory::create([
            'project_id' => $task->project_id,
            'project_task_id' => $task->id,
            'employee_id' => $employee->id,
        ]);
        $employeeIds[] = $employee->id;
    }

    return [$task, $employeeIds];
}

beforeEach(function () {
    $this->mock(FormatBoards::class, function ($mock) {
        $mock->shouldReceive('handle')->andReturn([]);
    });
    $this->mock(DetailCache::class, function ($mock) {
        $mock->shouldReceive('handle')->andReturn(['full_detail' => 'stub']);
    });

    // revertToDistribute hands the task to the configured 3D lead modeller, read from the
    // 'lead_3d_modeller' setting (getSettingByKey reads the 'setting' cache).
    $this->leadModeller = Employee::factory()->create();
    Cache::forever('setting', [
        ['key' => 'lead_3d_modeller', 'value' => $this->leadModeller->uid],
    ]);

    // The route is guarded by auth.session plus a role middleware that permits any of: root,
    // director, lead modeller, project manager, project manager admin. auth.session accepts an
    // RS256 bearer token or a Sanctum session; with no bearer token present it falls back to the
    // sanctum guard. The default actor holds root (one permitted role) and is used by the cases
    // that are not about role gating.
    Role::firstOrCreate(['name' => BaseRole::Root->value, 'guard_name' => 'sanctum']);
    $this->actor = User::factory()->create();
    $this->actor->assignRole(BaseRole::Root->value);
    actingAs($this->actor, 'sanctum');
});

describe('revertToDistribute (service)', function () {
    it('rewinds a WaitingApproval task back to WaitingDistribute', function () {
        $task = ProjectTask::factory()->create(['status' => TaskStatus::WaitingApproval->value]);

        $response = revertService()->revertToDistribute($task->project->uid, $task->uid);

        expect($response['error'])->toBeFalse()
            ->and($response['message'])->toBe('Success revert status to distribute');

        assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::WaitingDistribute->value,
        ]);
    });

    it('detaches the current task pics and their history on a successful revert', function () {
        [$task, $employeeIds] = revertTaskWithPics(2);

        // sanity: the pics and their history exist before the revert
        expect(ProjectTaskPic::where('project_task_id', $task->id)->count())->toBe(2)
            ->and(ProjectTaskPicHistory::where('project_task_id', $task->id)->count())->toBe(2);

        $response = revertService()->revertToDistribute($task->project->uid, $task->uid);

        expect($response['error'])->toBeFalse();
        assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::WaitingDistribute->value,
        ]);

        // every original pic and its history row is removed (the lead modeller replaces them)
        foreach ($employeeIds as $employeeId) {
            assertDatabaseMissing('project_task_pics', [
                'project_task_id' => $task->id,
                'employee_id' => $employeeId,
            ]);
            assertDatabaseMissing('project_task_pic_histories', [
                'project_task_id' => $task->id,
                'employee_id' => $employeeId,
            ]);
        }
    });

    it('assigns the task to the current lead modeller as the sole pic', function () {
        [$task] = revertTaskWithPics(2);

        revertService()->revertToDistribute($task->project->uid, $task->uid);

        // the lead modeller becomes the only pic, flagged WaitingToDistribute, with a history row
        assertDatabaseHas('project_task_pics', [
            'project_task_id' => $task->id,
            'employee_id' => $this->leadModeller->id,
            'status' => TaskPicStatus::WaitingToDistribute->value,
        ]);
        assertDatabaseHas('project_task_pic_histories', [
            'project_task_id' => $task->id,
            'employee_id' => $this->leadModeller->id,
        ]);
        expect(ProjectTaskPic::where('project_task_id', $task->id)->pluck('employee_id')->all())
            ->toBe([$this->leadModeller->id]);
    });

    it('fails and changes nothing when no lead modeller is configured', function () {
        Cache::forever('setting', []); // clear the lead_3d_modeller setting
        [$task, $employeeIds] = revertTaskWithPics(1);

        $response = revertService()->revertToDistribute($task->project->uid, $task->uid);

        expect($response['error'])->toBeTrue()
            ->and($response['message'])->toContain('Lead modeller is not set');

        // resolution happens before any write, so the task and its pics are untouched
        assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::WaitingApproval->value,
        ]);
        foreach ($employeeIds as $employeeId) {
            assertDatabaseHas('project_task_pics', [
                'project_task_id' => $task->id,
                'employee_id' => $employeeId,
            ]);
        }
    });

    it('keeps the pics when the revert is rejected for a non-WaitingApproval task', function () {
        $task = ProjectTask::factory()->create(['status' => TaskStatus::OnProgress->value]);
        $employee = Employee::factory()->create();
        ProjectTaskPic::create([
            'project_task_id' => $task->id,
            'employee_id' => $employee->id,
            'status' => TaskPicStatus::Approved->value,
        ]);
        ProjectTaskPicHistory::create([
            'project_id' => $task->project_id,
            'project_task_id' => $task->id,
            'employee_id' => $employee->id,
        ]);

        $response = revertService()->revertToDistribute($task->project->uid, $task->uid);

        expect($response['error'])->toBeTrue();
        assertDatabaseHas('project_task_pics', [
            'project_task_id' => $task->id,
            'employee_id' => $employee->id,
        ]);
        assertDatabaseHas('project_task_pic_histories', [
            'project_task_id' => $task->id,
            'employee_id' => $employee->id,
        ]);
    });

    it('fails and changes nothing when the project does not exist', function () {
        $task = ProjectTask::factory()->create(['status' => TaskStatus::WaitingApproval->value]);

        $response = revertService()->revertToDistribute('non-existent-project-uid', $task->uid);

        expect($response['error'])->toBeTrue()
            ->and($response['message'])->toContain('Project is not found');

        assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::WaitingApproval->value,
        ]);
    });

    it('fails when the task does not belong to the given project', function () {
        $task = ProjectTask::factory()->create(['status' => TaskStatus::WaitingApproval->value]);
        $otherProject = Project::factory()->create();

        $response = revertService()->revertToDistribute($otherProject->uid, $task->uid);

        expect($response['error'])->toBeTrue()
            ->and($response['message'])->toContain('Task is not found');

        assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::WaitingApproval->value,
        ]);
    });

    it('rejects reverting a task whose status is not WaitingApproval', function (int $status) {
        $task = ProjectTask::factory()->create(['status' => $status]);

        $response = revertService()->revertToDistribute($task->project->uid, $task->uid);

        expect($response['error'])->toBeTrue()
            ->and($response['message'])->toContain('Cannot revert to distribute');

        // status left exactly as it was
        assertDatabaseHas('project_tasks', ['id' => $task->id, 'status' => $status]);
    })->with([
        'on progress' => [TaskStatus::OnProgress->value],
        'already waiting distribute' => [TaskStatus::WaitingDistribute->value],
        'check by pm' => [TaskStatus::CheckByPm->value],
        'revise' => [TaskStatus::Revise->value],
        'completed' => [TaskStatus::Completed->value],
        'on hold' => [TaskStatus::OnHold->value],
    ]);
});

describe('revertToDistribute (e2e)', function () {
    it('reverts the task to WaitingDistribute for each permitted role', function (string $role) {
        revertActingAsRole($role);
        $task = ProjectTask::factory()->create(['status' => TaskStatus::WaitingApproval->value]);

        $response = $this->getJson(revertUrl($task->project->uid, $task->uid));

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Success revert status to distribute');

        assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::WaitingDistribute->value,
        ]);
    })->with(revertPermittedRoles());

    it('returns 400 and keeps the status when the task is not WaitingApproval', function () {
        $task = ProjectTask::factory()->create(['status' => TaskStatus::OnProgress->value]);

        $response = $this->getJson(revertUrl($task->project->uid, $task->uid));

        $response->assertStatus(400);
        expect($response->json('message'))->toContain('Cannot revert to distribute');

        assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::OnProgress->value,
        ]);
    });

    it('returns 400 when the project is not found', function () {
        $task = ProjectTask::factory()->create(['status' => TaskStatus::WaitingApproval->value]);

        $this->getJson(revertUrl('non-existent-uid', $task->uid))
            ->assertStatus(400);
    });

    it('rejects unauthenticated access', function () {
        $task = ProjectTask::factory()->create(['status' => TaskStatus::WaitingApproval->value]);

        // drop the beforeEach authentication for this case
        auth()->forgetGuards();

        $this->getJson(revertUrl($task->project->uid, $task->uid))
            ->assertUnauthorized();
    });

    it('forbids an authenticated user whose role is not permitted', function () {
        $task = ProjectTask::factory()->create(['status' => TaskStatus::WaitingApproval->value]);

        // authenticated, but with a role outside the permitted set -> the role middleware blocks it
        revertActingAsRole(BaseRole::Marketing->value);

        $this->getJson(revertUrl($task->project->uid, $task->uid))
            ->assertForbidden();

        assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::WaitingApproval->value,
        ]);
    });
});
