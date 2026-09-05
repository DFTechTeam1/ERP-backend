<?php

use App\Actions\Project\DetailCache;
use App\Enums\Production\ProjectStatus;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Modules\Company\Models\ProjectClass;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmployeePoint;
use Modules\Hrd\Models\EmployeePointProject;
use Modules\Hrd\Models\EmployeePointProjectDetail;
use Modules\Hrd\Models\EmployeeReward;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectPersonInCharge;
use Modules\Production\Models\ProjectTask;
use Modules\Production\Models\ProjectTaskPicHistory;
use Modules\Production\Services\ProjectService;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

/**
 * completeProject() point-recording flow (the reward-based rewrite).
 *
 * The intended flow, per the module owner:
 *   1. Update the project status.
 *   2. Store one feedback row per project person-in-charge (a project can have >1 PIC).
 *   3. Store the employee point chain: employee_points -> employee_point_projects ->
 *      employee_point_project_details, derived from the project's task PIC histories.
 *   4. employee_points.total_point must equal the SUM of that employee's
 *      employee_point_projects.total_point.
 *   5. employee_rewards must have a matching record.
 *
 * How the pieces feed each other:
 *   - PointRecordBasedOnReward reads project.taskPicHistories (NOT $data['points']) to decide
 *     who earned points; $data['points'] only supplies each worker's `additional_point`.
 *   - PMs are excluded; the "entertainment" roles decide employee_points.type.
 *   - Role membership is read via getSettingByKey(), which reads the 'setting' cache, so we
 *     seed it directly instead of touching the settings table.
 *   - DetailCache::handle() (full detail rebuild) is stubbed - it is orthogonal to point
 *     recording and heavy to set up.
 */
function cpService(): ProjectService
{
    return app(ProjectService::class);
}

function cpConfigureRoleSettings(int $projectManagerRoleId, int $entertainmentRoleId): void
{
    Cache::forever('setting', [
        ['key' => 'project_manager_role', 'value' => json_encode([$projectManagerRoleId])],
        ['key' => 'role_as_entertainment', 'value' => json_encode([$entertainmentRoleId])],
    ]);
}

function cpEmployeeWithRole(Role $role): Employee
{
    $employee = Employee::factory()->withUser()->create();
    User::where('employee_id', $employee->id)->firstOrFail()->assignRole($role);

    return $employee->refresh();
}

/**
 * Attach $count tasks to $project and record a task-PIC-history row per task for $employee.
 * These histories are what PointRecordBasedOnReward turns into points.
 *
 * @return array<int, int> created task ids
 */
function cpAssignTasks(Project $project, Employee $employee, int $count): array
{
    $taskIds = [];
    for ($i = 0; $i < $count; $i++) {
        $task = ProjectTask::factory()->create([
            'project_id' => $project->id,
            'name' => "Task {$i}",
        ]);
        ProjectTaskPicHistory::create([
            'project_id' => $project->id,
            'project_task_id' => $task->id,
            'employee_id' => $employee->id,
        ]);
        $taskIds[] = $task->id;
    }

    return $taskIds;
}

beforeEach(function () {
    // Stub the full-detail cache rebuild - orthogonal to point recording and heavy to set up.
    $this->mock(DetailCache::class, function ($mock) {
        $mock->shouldReceive('handle')->andReturn([]);
    });

    $this->pmRole = Role::firstOrCreate(['name' => 'cp-project-manager', 'guard_name' => 'sanctum']);
    $this->productionRole = Role::firstOrCreate(['name' => 'cp-production', 'guard_name' => 'sanctum']);
    $this->entertainmentRole = Role::firstOrCreate(['name' => 'cp-entertainment', 'guard_name' => 'sanctum']);

    cpConfigureRoleSettings($this->pmRole->id, $this->entertainmentRole->id);
});

describe('completeProject point recording', function () {
    it('records the full point/reward chain and completes the project for a production worker', function () {
        $class = ProjectClass::factory()->create(['name' => 'Class A', 'reward' => 50000]);
        $project = Project::factory()->create([
            'project_class_id' => $class->id,
            'status' => ProjectStatus::OnGoing->value,
        ]);

        $worker = cpEmployeeWithRole($this->productionRole);
        ProjectPersonInCharge::create(['project_id' => $project->id, 'pic_id' => $worker->id]);
        cpAssignTasks($project, $worker, 2);

        actingAs(User::where('employee_id', $worker->id)->firstOrFail());

        $data = [
            'feedback' => 'Great execution',
            'points' => [
                ['uid' => $worker->uid, 'additional_point' => 0],
            ],
        ];

        $response = cpService()->completeProject($data, $project->uid);

        expect($response['error'])->toBeFalse();

        // (1) status -> Completed (single PIC, single feedback recorded)
        assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => ProjectStatus::Completed->value,
        ]);

        // (2) feedback stored for the PIC
        assertDatabaseHas('project_feedback', [
            'project_id' => $project->id,
            'pic_id' => $worker->id,
            'feedback' => 'Great execution',
        ]);

        // (3) employee_points / employee_point_projects / details
        $employeePoint = EmployeePoint::where('employee_id', $worker->id)->first();
        expect($employeePoint)->not->toBeNull()
            ->and($employeePoint->type)->toBe('production');

        $pointProject = EmployeePointProject::where('project_id', $project->id)->first();
        expect($pointProject)->not->toBeNull()
            ->and((int) $pointProject->total_point)->toBe(2)
            ->and((int) $pointProject->employee_point_id)->toBe($employeePoint->id); // linkage

        expect(EmployeePointProjectDetail::where('point_id', $pointProject->id)->count())->toBe(2);

        // (4) employee_points.total_point == SUM(employee_point_projects.total_point)
        $sum = (int) EmployeePointProject::where('employee_point_id', $employeePoint->id)->sum('total_point');
        expect((int) $employeePoint->total_point)->toBe($sum)
            ->and((int) $employeePoint->total_point)->toBe(2);

        // (5) reward recorded
        assertDatabaseHas('employee_rewards', [
            'employee_id' => $worker->id,
            'project_id' => $project->id,
            'employee_point_project_id' => $pointProject->id,
            'base_reward' => 50000,
            'point' => 2,
            'additional_point' => 0,
            'total_point' => 2,
            'total_reward' => 100000,
            'project_class_name' => 'Class A',
        ]);
    });

    it('excludes project managers from point and reward recording', function () {
        $class = ProjectClass::factory()->create(['reward' => 10000]);
        $project = Project::factory()->create(['project_class_id' => $class->id]);

        $worker = cpEmployeeWithRole($this->productionRole);
        $pm = cpEmployeeWithRole($this->pmRole);

        ProjectPersonInCharge::create(['project_id' => $project->id, 'pic_id' => $worker->id]);
        cpAssignTasks($project, $worker, 1);
        cpAssignTasks($project, $pm, 3);

        actingAs(User::where('employee_id', $worker->id)->firstOrFail());

        $response = cpService()->completeProject([
            'feedback' => 'ok',
            'points' => [
                ['uid' => $worker->uid, 'additional_point' => 0],
                ['uid' => $pm->uid, 'additional_point' => 0],
            ],
        ], $project->uid);

        expect($response['error'])->toBeFalse();

        // worker earned points, PM did not
        assertDatabaseHas('employee_points', ['employee_id' => $worker->id]);
        assertDatabaseMissing('employee_points', ['employee_id' => $pm->id]);
        assertDatabaseMissing('employee_rewards', ['employee_id' => $pm->id]);
        expect(EmployeeReward::where('employee_id', $worker->id)->count())->toBe(1);
    });

    it('stores the employee_points type as entertainment for entertainment-role workers', function () {
        $class = ProjectClass::factory()->create(['reward' => 10000]);
        $project = Project::factory()->create(['project_class_id' => $class->id]);

        $worker = cpEmployeeWithRole($this->entertainmentRole);
        ProjectPersonInCharge::create(['project_id' => $project->id, 'pic_id' => $worker->id]);
        cpAssignTasks($project, $worker, 1);

        actingAs(User::where('employee_id', $worker->id)->firstOrFail());

        cpService()->completeProject([
            'feedback' => 'ok',
            'points' => [['uid' => $worker->uid, 'additional_point' => 0]],
        ], $project->uid);

        assertDatabaseHas('employee_points', [
            'employee_id' => $worker->id,
            'type' => 'entertainment',
        ]);
    });

    it('adds additional_point into the reward and the employee total point', function () {
        $class = ProjectClass::factory()->create(['name' => 'Class B', 'reward' => 50000]);
        $project = Project::factory()->create(['project_class_id' => $class->id]);

        $worker = cpEmployeeWithRole($this->productionRole);
        ProjectPersonInCharge::create(['project_id' => $project->id, 'pic_id' => $worker->id]);
        cpAssignTasks($project, $worker, 2); // point = 2 tasks

        actingAs(User::where('employee_id', $worker->id)->firstOrFail());

        cpService()->completeProject([
            'feedback' => 'ok',
            'points' => [['uid' => $worker->uid, 'additional_point' => 3]],
        ], $project->uid);

        // reward: point=2, additional=3, total_point=5, total_reward = 50000 * 5
        assertDatabaseHas('employee_rewards', [
            'employee_id' => $worker->id,
            'project_id' => $project->id,
            'base_reward' => 50000,
            'point' => 2,
            'additional_point' => 3,
            'total_point' => 5,
            'total_reward' => 250000,
        ]);

        // employee_point_projects.total_point is the FULL per-project total (regular + additional)
        $pointProject = EmployeePointProject::where('project_id', $project->id)->first();
        expect((int) $pointProject->total_point)->toBe(5)     // 2 regular + 3 additional
            ->and((int) $pointProject->additional_point)->toBe(3)
            ->and((int) $pointProject->original_point)->toBe(2);

        // (4) employee_points.total_point == SUM(employee_point_projects.total_point),
        // and this holds even when additional_point > 0
        $employeePoint = EmployeePoint::where('employee_id', $worker->id)->first();
        $projectSum = (int) EmployeePointProject::where('employee_point_id', $employeePoint->id)->sum('total_point');
        expect((int) $employeePoint->total_point)->toBe(5)
            ->and($projectSum)->toBe(5);
    });

    it('leaves the project PartialComplete when not all PICs have submitted feedback', function () {
        $class = ProjectClass::factory()->create(['reward' => 10000]);
        $project = Project::factory()->create([
            'project_class_id' => $class->id,
            'status' => ProjectStatus::OnGoing->value,
        ]);

        $worker = cpEmployeeWithRole($this->productionRole);
        $otherPic = Employee::factory()->create();

        // two PICs, but completeProject only records feedback for the acting user
        ProjectPersonInCharge::create(['project_id' => $project->id, 'pic_id' => $worker->id]);
        ProjectPersonInCharge::create(['project_id' => $project->id, 'pic_id' => $otherPic->id]);
        cpAssignTasks($project, $worker, 1);

        actingAs(User::where('employee_id', $worker->id)->firstOrFail());

        cpService()->completeProject([
            'feedback' => 'ok',
            'points' => [['uid' => $worker->uid, 'additional_point' => 0]],
        ], $project->uid);

        assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => ProjectStatus::PartialComplete->value,
        ]);

        // points are still recorded regardless of feedback completeness
        assertDatabaseHas('employee_points', ['employee_id' => $worker->id]);
    });

    it('records nothing and leaves the project PartialComplete when no points are provided', function () {
        $class = ProjectClass::factory()->create(['reward' => 10000]);
        $project = Project::factory()->create([
            'project_class_id' => $class->id,
            'status' => ProjectStatus::OnGoing->value,
        ]);

        $actor = cpEmployeeWithRole($this->productionRole);
        actingAs(User::where('employee_id', $actor->id)->firstOrFail());

        $response = cpService()->completeProject([
            'feedback' => 'ok',
            'points' => [],
        ], $project->uid);

        expect($response['error'])->toBeFalse();

        assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => ProjectStatus::PartialComplete->value,
        ]);
        expect(EmployeePoint::count())->toBe(0)
            ->and(EmployeeReward::count())->toBe(0);
        assertDatabaseMissing('project_feedback', ['project_id' => $project->id]);
    });
});
