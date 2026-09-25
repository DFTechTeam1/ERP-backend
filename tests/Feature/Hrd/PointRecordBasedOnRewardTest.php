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
 * Direct tests for the PointRecordBasedOnReward action (the "Pot Produksi Fixed per Kelas" rewrite).
 *
 * The action derives who earned points from the project's taskPicHistories (NOT from the
 * $points argument). $points only carries each worker's additional_point, keyed by employee uid.
 *
 * Points (unchanged):
 *   - regular point       = number of task-PIC-history rows for that employee
 *   - total_point         = regular point + additional_point
 *   - employee_point_project.total_point == total_point (original_point holds the regular part)
 *   - employee_points.total_point        == SUM of that employee's employee_point_projects.total_point
 *   - one employee_point_project_detail row per task
 *   - project managers are excluded entirely
 *   - employee_points.type is "entertainment" for entertainment-role workers, else "production"
 *
 * Reward (the new schema, from docs/Simulasi_Baseline_Pot_Produksi_Fixed_DFactory.xlsx):
 *   - the pot is FIXED per class and read from project_classes.reward (stored as base_reward)
 *   - each production worker's reward = ROUND((worker total_point / total production points) x pot)
 *   - the last production row absorbs the rounding remainder so the event payout == pot exactly
 *   - entertainment/VJ workers are outside the production pot and get reward 0
 *   - no production points at all -> every reward stays 0 (Excel "PERLU KEPUTUSAN MANUAL")
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

describe('PointRecordBasedOnReward point recording', function () {
    it('records one point per task with the correct total-point invariant', function () {
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
    });

    it('folds additional_point into total_point', function () {
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

describe('PointRecordBasedOnReward pot distribution', function () {
    it('gives the whole pot to a sole production worker', function () {
        $class = ProjectClass::factory()->create(['name' => 'Class A', 'reward' => 50000]);
        $project = Project::factory()->create(['project_class_id' => $class->id]);

        $worker = prbrEmployeeWithRole($this->productionRole);
        prbrAssignTasks($project, $worker, 3);

        PointRecordBasedOnReward::run($project->id, [
            ['uid' => $worker->uid, 'additional_point' => 0],
        ]);

        // sole worker => 100% of the fixed pot, regardless of how many points
        assertDatabaseHas('employee_rewards', [
            'employee_id' => $worker->id,
            'project_id' => $project->id,
            'base_reward' => 50000,
            'point' => 3,
            'additional_point' => 0,
            'total_point' => 3,
            'total_reward' => 50000,
            'project_class_name' => 'Class A',
        ]);
    });

    it('splits the fixed pot by point-share and never exceeds the pot', function () {
        $class = ProjectClass::factory()->create(['name' => 'Class C', 'reward' => 10000]);
        $project = Project::factory()->create(['project_class_id' => $class->id]);

        $alice = prbrEmployeeWithRole($this->productionRole);
        $bob = prbrEmployeeWithRole($this->productionRole);

        prbrAssignTasks($project, $alice, 2);      // total_point 2
        prbrAssignTasks($project, $bob, 4);        // total_point 4 + 1 additional = 5

        PointRecordBasedOnReward::run($project->id, [
            ['uid' => $alice->uid, 'additional_point' => 0],
            ['uid' => $bob->uid, 'additional_point' => 1],
        ]);

        // total production points = 7; pot = 10000
        // alice = round(2/7 * 10000) = 2857
        // bob   = last row, absorbs remainder = 10000 - 2857 = 7143
        assertDatabaseHas('employee_rewards', [
            'employee_id' => $alice->id,
            'base_reward' => 10000,
            'total_point' => 2,
            'total_reward' => 2857,
        ]);
        assertDatabaseHas('employee_rewards', [
            'employee_id' => $bob->id,
            'base_reward' => 10000,
            'total_point' => 5,
            'total_reward' => 7143,
        ]);

        // the event payout equals the pot exactly (rounding absorbed, no drift)
        $paid = (float) EmployeeReward::where('project_id', $project->id)->sum('total_reward');
        expect($paid)->toBe(10000.0);
    });

    it('absorbs rounding on the last row so an indivisible pot still sums exactly', function () {
        $class = ProjectClass::factory()->create(['name' => 'Class C', 'reward' => 10000]);
        $project = Project::factory()->create(['project_class_id' => $class->id]);

        $a = prbrEmployeeWithRole($this->productionRole);
        $b = prbrEmployeeWithRole($this->productionRole);
        $c = prbrEmployeeWithRole($this->productionRole);

        // 1 + 1 + 1 points => 10000 / 3 does not divide evenly
        prbrAssignTasks($project, $a, 1);
        prbrAssignTasks($project, $b, 1);
        prbrAssignTasks($project, $c, 1);

        PointRecordBasedOnReward::run($project->id, [
            ['uid' => $a->uid, 'additional_point' => 0],
            ['uid' => $b->uid, 'additional_point' => 0],
            ['uid' => $c->uid, 'additional_point' => 0],
        ]);

        // round(1/3 * 10000) = 3333 for the first two; last absorbs 10000 - 6666 = 3334
        assertDatabaseHas('employee_rewards', ['employee_id' => $a->id, 'total_reward' => 3333]);
        assertDatabaseHas('employee_rewards', ['employee_id' => $b->id, 'total_reward' => 3333]);
        assertDatabaseHas('employee_rewards', ['employee_id' => $c->id, 'total_reward' => 3334]);

        $paid = (float) EmployeeReward::where('project_id', $project->id)->sum('total_reward');
        expect($paid)->toBe(10000.0);
    });

    it('keeps entertainment workers out of the production pot', function () {
        $class = ProjectClass::factory()->create(['name' => 'Class B', 'reward' => 10000]);
        $project = Project::factory()->create(['project_class_id' => $class->id]);

        $producer = prbrEmployeeWithRole($this->productionRole);
        $entertainer = prbrEmployeeWithRole($this->entertainmentRole);

        prbrAssignTasks($project, $producer, 2);
        prbrAssignTasks($project, $entertainer, 3);

        PointRecordBasedOnReward::run($project->id, [
            ['uid' => $producer->uid, 'additional_point' => 0],
            ['uid' => $entertainer->uid, 'additional_point' => 0],
        ]);

        // the sole production worker takes the whole pot; the entertainer gets nothing
        assertDatabaseHas('employee_rewards', [
            'employee_id' => $producer->id,
            'total_reward' => 10000,
        ]);
        assertDatabaseHas('employee_rewards', [
            'employee_id' => $entertainer->id,
            'total_reward' => 0,
        ]);

        $paid = (float) EmployeeReward::where('project_id', $project->id)->sum('total_reward');
        expect($paid)->toBe(10000.0);
    });

    it('pays nothing when the class has no pot configured', function () {
        $class = ProjectClass::factory()->create(['name' => 'Class D', 'reward' => 0]);
        $project = Project::factory()->create(['project_class_id' => $class->id]);

        $worker = prbrEmployeeWithRole($this->productionRole);
        prbrAssignTasks($project, $worker, 4);

        PointRecordBasedOnReward::run($project->id, [
            ['uid' => $worker->uid, 'additional_point' => 0],
        ]);

        // points are still recorded, but there is no pot to split
        assertDatabaseHas('employee_point_projects', [
            'project_id' => $project->id,
            'total_point' => 4,
        ]);
        assertDatabaseHas('employee_rewards', [
            'employee_id' => $worker->id,
            'base_reward' => 0,
            'total_reward' => 0,
        ]);
    });

    it('freezes the recorded reward so a later class price change does not alter it', function () {
        $class = ProjectClass::factory()->create(['name' => 'Class A', 'reward' => 50000]);
        $project = Project::factory()->create(['project_class_id' => $class->id]);

        $worker = prbrEmployeeWithRole($this->productionRole);
        prbrAssignTasks($project, $worker, 2);

        PointRecordBasedOnReward::run($project->id, [
            ['uid' => $worker->uid, 'additional_point' => 0],
        ]);

        // HR raises the class pot the following month (query-builder update, no model events)
        ProjectClass::where('id', $class->id)->update(['reward' => 999999]);

        // the already-recorded reward keeps this month's pot: it was snapshotted at record time
        // (employee_rewards.base_reward / total_reward), never re-derived from the live class price
        $reward = EmployeeReward::where('employee_id', $worker->id)
            ->where('project_id', $project->id)
            ->first();

        expect($reward)->not->toBeNull()
            ->and((float) $reward->base_reward)->toBe(50000.0)
            ->and((float) $reward->total_reward)->toBe(50000.0);

        assertDatabaseHas('employee_rewards', [
            'employee_id' => $worker->id,
            'project_id' => $project->id,
            'base_reward' => 50000,
            'total_reward' => 50000,
            'project_class_name' => 'Class A',
        ]);
    });
});
