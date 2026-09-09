<?php

use App\Actions\Project\DetailCache;
use App\Enums\Production\EventType;
use App\Enums\Production\ProjectActivityItem;
use App\Enums\Production\ProjectStatus;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Modules\Company\Models\ProjectClass;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmployeePoint;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectActivity;
use Modules\Production\Models\ProjectPersonInCharge;
use Modules\Production\Models\ProjectTask;
use Modules\Production\Models\ProjectTaskPicHistory;
use Modules\Production\Services\ProjectService;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

/**
 * ProjectService activity + point-recording wiring.
 *
 * The feature under test adds project-activity logging to the service:
 *   - changeStatus()  ALWAYS records a ChangeStatus activity.
 *   - updateBasic()   records a ChangeProjectClass activity ONLY when the project class changes.
 *   - completeProject() runs PointRecordBasedOnReward (point/reward chain).
 *
 * ProjectActivityRecord resolves the actor from Auth::id() -> the employee name, and writes a
 * project_activities row via the project's activities() relation.
 *
 * DetailCache::handle() (the heavy full-detail rebuild) is stubbed - it is orthogonal to what we
 * assert here. Role membership is read from the 'setting' cache, so we seed it directly.
 *
 * The exhaustive completeProject point/reward coverage lives in
 * tests/Feature/Production/CompleteProjectPointRecordingTest.php; here we only assert that
 * completeProject triggers that chain.
 */
function psaService(): ProjectService
{
    return app(ProjectService::class);
}

function psaActor(Role $role): User
{
    $employee = Employee::factory()->withUser()->create(['name' => 'Actor '.uniqid()]);
    $user = User::where('employee_id', $employee->id)->firstOrFail();
    $user->assignRole($role);
    actingAs($user);

    return $user;
}

beforeEach(function () {
    $this->mock(DetailCache::class, function ($mock) {
        $mock->shouldReceive('handle')->andReturn([]);
    });

    $this->staffRole = Role::firstOrCreate(['name' => 'psa-staff', 'guard_name' => 'sanctum']);
    $this->pmRole = Role::firstOrCreate(['name' => 'psa-project-manager', 'guard_name' => 'sanctum']);
    $this->entertainmentRole = Role::firstOrCreate(['name' => 'psa-entertainment', 'guard_name' => 'sanctum']);

    Cache::forever('setting', [
        ['key' => 'project_manager_role', 'value' => json_encode([$this->pmRole->id])],
        ['key' => 'role_as_entertainment', 'value' => json_encode([$this->entertainmentRole->id])],
    ]);
});

describe('changeStatus activity recording', function () {
    it('records a ChangeStatus activity attributed to the acting user', function () {
        $actor = psaActor($this->staffRole);
        $actorEmployee = Employee::find($actor->employee_id);

        $project = Project::factory()->create(['status' => ProjectStatus::Revise->value]);

        $response = psaService()->changeStatus([
            'status' => ProjectStatus::OnGoing->value,
            'base_status' => ProjectStatus::Revise->value,
        ], $project->uid);

        expect($response['error'])->toBeFalse();

        // status persisted
        assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => ProjectStatus::OnGoing->value,
        ]);

        // exactly one activity, correct action + actor
        expect(ProjectActivity::where('project_id', $project->id)->count())->toBe(1);
        assertDatabaseHas('project_activities', [
            'project_id' => $project->id,
            'action' => ProjectActivityItem::ChangeStatus->description(
                ProjectStatus::Revise->label(),
                ProjectStatus::OnGoing->label(),
            ),
            'actor' => $actorEmployee->name,
        ]);
    });
});

describe('updateBasic activity recording', function () {
    it('records a ChangeProjectClass activity when the project class changes', function () {
        $actor = psaActor($this->staffRole);
        $actorEmployee = Employee::find($actor->employee_id);

        $classA = ProjectClass::factory()->create(['name' => 'Class A']);
        $classB = ProjectClass::factory()->create(['name' => 'Class B']);

        $project = Project::factory()->create([
            'project_class_id' => $classA->id,
            'classification' => $classA->name,
        ]);

        $response = psaService()->updateBasic([
            'name' => 'Updated project name',
            'date' => '2026-10-01',
            'event_type' => EventType::Wedding->value,
            'classification' => (string) $classB->id, // switch A -> B (repo resolves by id)
        ], $project->uid);

        expect($response['error'])->toBeFalse();

        assertDatabaseHas('projects', [
            'id' => $project->id,
            'project_class_id' => $classB->id,
        ]);

        expect(ProjectActivity::where('project_id', $project->id)->count())->toBe(1);
        assertDatabaseHas('project_activities', [
            'project_id' => $project->id,
            'action' => ProjectActivityItem::ChangeProjectClass->description('Class A', 'Class B'),
            'actor' => $actorEmployee->name,
        ]);
    });

    it('records no activity when the project class is unchanged', function () {
        psaActor($this->staffRole);

        $classA = ProjectClass::factory()->create(['name' => 'Class A']);

        $project = Project::factory()->create([
            'project_class_id' => $classA->id,
            'classification' => $classA->name,
        ]);

        $response = psaService()->updateBasic([
            'name' => 'Renamed, same class',
            'date' => '2026-10-01',
            'event_type' => EventType::Wedding->value,
            'classification' => (string) $classA->id, // same class (repo resolves by id)
        ], $project->uid);

        expect($response['error'])->toBeFalse();

        expect(ProjectActivity::where('project_id', $project->id)->count())->toBe(0);
        // sanity: the update itself still went through
        assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Renamed, same class',
        ]);
    });
});

describe('completeProject point recording', function () {
    it('runs PointRecordBasedOnReward and records the employee point/reward chain', function () {
        $class = ProjectClass::factory()->create(['name' => 'Class A', 'reward' => 50000]);
        $project = Project::factory()->create([
            'project_class_id' => $class->id,
            'status' => ProjectStatus::OnGoing->value,
        ]);

        $worker = Employee::factory()->withUser()->create();
        User::where('employee_id', $worker->id)->firstOrFail()->assignRole($this->staffRole);
        actingAs(User::where('employee_id', $worker->id)->firstOrFail());

        ProjectPersonInCharge::create(['project_id' => $project->id, 'pic_id' => $worker->id]);

        for ($i = 0; $i < 2; $i++) {
            $task = ProjectTask::factory()->create(['project_id' => $project->id, 'name' => "Task {$i}"]);
            ProjectTaskPicHistory::create([
                'project_id' => $project->id,
                'project_task_id' => $task->id,
                'employee_id' => $worker->id,
            ]);
        }

        $response = psaService()->completeProject([
            'feedback' => 'Great execution',
            'points' => [['uid' => $worker->uid, 'additional_point' => 0]],
        ], $project->uid);

        expect($response['error'])->toBeFalse();

        // completeProject transitions single-PIC projects to Completed
        assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => ProjectStatus::Completed->value,
        ]);

        // point/reward chain recorded (proof PointRecordBasedOnReward ran)
        $employeePoint = EmployeePoint::where('employee_id', $worker->id)->first();
        expect($employeePoint)->not->toBeNull()
            ->and((int) $employeePoint->total_point)->toBe(2);

        assertDatabaseHas('employee_rewards', [
            'employee_id' => $worker->id,
            'project_id' => $project->id,
            'base_reward' => 50000,
            'total_point' => 2,
            'total_reward' => 100000,
        ]);
    });
});
