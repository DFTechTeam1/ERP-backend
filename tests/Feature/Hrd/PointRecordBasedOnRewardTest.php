<?php

use App\Actions\Hrd\PointRecordBasedOnReward;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Modules\Company\Models\ProjectClass;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmployeePoint;
use Modules\Hrd\Models\EmployeePointProject;
use Modules\Hrd\Models\EmployeePointProjectDetail;
use Modules\Hrd\Models\EmployeeReward;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectTask;
use Modules\Production\Models\ProjectTaskPicHistory;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

/**
 * Direct tests for the PointRecordBasedOnReward action (the reward-based point rewrite).
 *
 * The action derives who earned points from the project's taskPicHistories (NOT from the
 * $points argument). $points only carries each worker's additional_point, keyed by employee uid.
 *
 * Point math the action must guarantee:
 *   - regular point       = number of task-PIC-history rows for that employee
 *   - total_point         = regular point + additional_point
 *   - employee_point_project.total_point == total_point (original_point holds the regular part)
 *   - employee_points.total_point        == SUM of that employee's employee_point_projects.total_point
 *   - one employee_point_project_detail row per task
 *   - reward.total_reward == projectClass.reward * total_point
 *   - project managers are excluded entirely
 *   - employee_points.type is "entertainment" for entertainment-role workers, else "production"
 *
 * Role membership is read via getSettingByKey() -> the 'setting' cache, so we seed it directly.
 */
function prbrConfigureRoles(int $projectManagerRoleId, int $entertainmentRoleId): void
{
    Cache::forever('setting', [
        ['key' => 'project_manager_role', 'value' => json_encode([$projectManagerRoleId])],
        ['key' => 'role_as_entertainment', 'value' => json_encode([$entertainmentRoleId])],
    ]);
}

function prbrEmployeeWithRole(Role $role): Employee
{
    $employee = Employee::factory()->withUser()->create();
    User::where('employee_id', $employee->id)->firstOrFail()->assignRole($role);

    return $employee->refresh();
}

/**
 * Attach $count tasks to $project and record one task-PIC-history row per task for $employee.
 * These histories are what the action turns into points.
 *
 * @return array<int, int> created task ids
 */
function prbrAssignTasks(Project $project, Employee $employee, int $count): array
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
    $this->pmRole = Role::firstOrCreate(['name' => 'prbr-project-manager', 'guard_name' => 'sanctum']);
    $this->productionRole = Role::firstOrCreate(['name' => 'prbr-production', 'guard_name' => 'sanctum']);
    $this->entertainmentRole = Role::firstOrCreate(['name' => 'prbr-entertainment', 'guard_name' => 'sanctum']);

    prbrConfigureRoles($this->pmRole->id, $this->entertainmentRole->id);
});

describe('PointRecordBasedOnReward point math', function () {
    it('records one point per task with the correct reward and total-point invariant', function () {
        $class = ProjectClass::factory()->create(['name' => 'Class A', 'reward' => 50000]);
        $project = Project::factory()->create(['project_class_id' => $class->id]);

        $worker = prbrEmployeeWithRole($this->productionRole);
        prbrAssignTasks($project, $worker, 3);

        PointRecordBasedOnReward::run($project->id, [
            ['uid' => $worker->uid, 'additional_point' => 0],
        ]);

        $employeePoint = EmployeePoint::where('employee_id', $worker->id)->first();
        expect($employeePoint)->not->toBeNull()
            ->and($employeePoint->type)->toBe('production');

        $pointProject = EmployeePointProject::where('project_id', $project->id)->first();
        expect($pointProject)->not->toBeNull()
            ->and((int) $pointProject->total_point)->toBe(3)
            ->and((int) $pointProject->original_point)->toBe(3)
            ->and((int) $pointProject->additional_point)->toBe(0)
            // linkage: the action must re-link to the employee_point row it fetched/created,
            // NOT the pre-existing singlePoint id captured during mapping (0 for first-timers).
            ->and((int) $pointProject->employee_point_id)->toBe($employeePoint->id);

        // one detail row per task
        expect(EmployeePointProjectDetail::where('point_id', $pointProject->id)->count())->toBe(3);

        // employee total == SUM of project totals
        $sum = (int) EmployeePointProject::where('employee_point_id', $employeePoint->id)->sum('total_point');
        expect((int) $employeePoint->total_point)->toBe($sum)
            ->and((int) $employeePoint->total_point)->toBe(3);

        // reward: base_reward * total_point
        assertDatabaseHas('employee_rewards', [
            'employee_id' => $worker->id,
            'project_id' => $project->id,
            'employee_point_project_id' => $pointProject->id,
            'base_reward' => 50000,
            'point' => 3,
            'additional_point' => 0,
            'total_point' => 3,
            'total_reward' => 150000,
            'project_class_name' => 'Class A',
        ]);
    });

    it('folds additional_point into total_point and the reward', function () {
        $class = ProjectClass::factory()->create(['name' => 'Class B', 'reward' => 40000]);
        $project = Project::factory()->create(['project_class_id' => $class->id]);

        $worker = prbrEmployeeWithRole($this->productionRole);
        prbrAssignTasks($project, $worker, 2); // regular point = 2

        PointRecordBasedOnReward::run($project->id, [
            ['uid' => $worker->uid, 'additional_point' => 3],
        ]);

        $pointProject = EmployeePointProject::where('project_id', $project->id)->first();
        expect((int) $pointProject->original_point)->toBe(2)
            ->and((int) $pointProject->additional_point)->toBe(3)
            ->and((int) $pointProject->total_point)->toBe(5); // 2 + 3

        $employeePoint = EmployeePoint::where('employee_id', $worker->id)->first();
        $sum = (int) EmployeePointProject::where('employee_point_id', $employeePoint->id)->sum('total_point');
        expect((int) $employeePoint->total_point)->toBe(5)
            ->and($sum)->toBe(5);

        // reward: 40000 * 5
        assertDatabaseHas('employee_rewards', [
            'employee_id' => $worker->id,
            'project_id' => $project->id,
            'base_reward' => 40000,
            'point' => 2,
            'additional_point' => 3,
            'total_point' => 5,
            'total_reward' => 200000,
        ]);
    });

    it('records each worker independently with its own correct point total', function () {
        $class = ProjectClass::factory()->create(['name' => 'Class C', 'reward' => 10000]);
        $project = Project::factory()->create(['project_class_id' => $class->id]);

        $alice = prbrEmployeeWithRole($this->productionRole);
        $bob = prbrEmployeeWithRole($this->productionRole);

        prbrAssignTasks($project, $alice, 2);
        prbrAssignTasks($project, $bob, 4);

        PointRecordBasedOnReward::run($project->id, [
            ['uid' => $alice->uid, 'additional_point' => 0],
            ['uid' => $bob->uid, 'additional_point' => 1],
        ]);

        $alicePoint = EmployeePoint::where('employee_id', $alice->id)->first();
        $bobPoint = EmployeePoint::where('employee_id', $bob->id)->first();

        expect((int) $alicePoint->total_point)->toBe(2)
            ->and((int) $bobPoint->total_point)->toBe(5); // 4 tasks + 1 additional

        $aliceProject = EmployeePointProject::where('employee_point_id', $alicePoint->id)->first();
        $bobProject = EmployeePointProject::where('employee_point_id', $bobPoint->id)->first();

        expect(EmployeePointProjectDetail::where('point_id', $aliceProject->id)->count())->toBe(2)
            ->and(EmployeePointProjectDetail::where('point_id', $bobProject->id)->count())->toBe(4);

        // rewards are per worker: alice 10000*2, bob 10000*5
        assertDatabaseHas('employee_rewards', [
            'employee_id' => $alice->id,
            'total_point' => 2,
            'total_reward' => 20000,
        ]);
        assertDatabaseHas('employee_rewards', [
            'employee_id' => $bob->id,
            'total_point' => 5,
            'total_reward' => 50000,
        ]);
    });

    it('excludes project managers from every point and reward table', function () {
        $class = ProjectClass::factory()->create(['reward' => 10000]);
        $project = Project::factory()->create(['project_class_id' => $class->id]);

        $worker = prbrEmployeeWithRole($this->productionRole);
        $pm = prbrEmployeeWithRole($this->pmRole);

        prbrAssignTasks($project, $worker, 1);
        prbrAssignTasks($project, $pm, 5);

        PointRecordBasedOnReward::run($project->id, [
            ['uid' => $worker->uid, 'additional_point' => 0],
            ['uid' => $pm->uid, 'additional_point' => 0],
        ]);

        assertDatabaseHas('employee_points', ['employee_id' => $worker->id]);
        assertDatabaseMissing('employee_points', ['employee_id' => $pm->id]);
        assertDatabaseMissing('employee_rewards', ['employee_id' => $pm->id]);
        expect(EmployeeReward::where('employee_id', $worker->id)->count())->toBe(1);
    });

    it('tags entertainment-role workers with the entertainment employee-point type', function () {
        $class = ProjectClass::factory()->create(['reward' => 10000]);
        $project = Project::factory()->create(['project_class_id' => $class->id]);

        $worker = prbrEmployeeWithRole($this->entertainmentRole);
        prbrAssignTasks($project, $worker, 1);

        PointRecordBasedOnReward::run($project->id, [
            ['uid' => $worker->uid, 'additional_point' => 0],
        ]);

        assertDatabaseHas('employee_points', [
            'employee_id' => $worker->id,
            'type' => 'entertainment',
        ]);
    });
});
