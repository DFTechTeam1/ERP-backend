<?php

use App\Actions\Hrd\RecordPmVjReward;
use Modules\Company\Models\ProjectClass;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmployeeReward;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectPersonInCharge;
use Modules\Production\Models\ProjectVj;

use function Pest\Laravel\assertDatabaseHas;

/**
 * Direct tests for RecordPmVjReward - the fixed PM and VJ reward recording (not point-based).
 *
 * PM: project_classes.pm_reward is the total PM pot split by headcount (1 PM: Lead 100%;
 * 2 PM: 70/30; 3 PM: 50/25/25), Lead identified by ProjectPersonInCharge.is_lead (falling back
 * to the earliest-assigned PIC). The last row absorbs the rounding remainder.
 * VJ: project_classes.vj_reward is a fixed amount PER VJ.
 * Both are stored in employee_rewards with role 'pm' / 'vj' and zeroed point columns.
 */
function pmvjClass(array $overrides = []): ProjectClass
{
    return ProjectClass::factory()->create(array_merge([
        'name' => 'Class B',
        'reward' => 1000000,
        'pm_reward' => 1000000,
        'vj_reward' => 125000,
    ], $overrides));
}

function pmvjAddPic(Project $project, Employee $employee, bool $isLead = false): ProjectPersonInCharge
{
    return ProjectPersonInCharge::create([
        'project_id' => $project->id,
        'pic_id' => $employee->id,
        'is_lead' => $isLead,
    ]);
}

function pmvjAddVj(Project $project, Employee $employee): ProjectVj
{
    return ProjectVj::create([
        'project_id' => $project->id,
        'employee_id' => $employee->id,
        'created_by' => 0,
    ]);
}

describe('RecordPmVjReward - PM pot split', function () {
    it('gives the whole PM pot to a sole PM', function () {
        $class = pmvjClass(['pm_reward' => 1000000]);
        $project = Project::factory()->create(['project_class_id' => $class->id]);
        $pm = Employee::factory()->create();
        pmvjAddPic($project, $pm, isLead: true);

        RecordPmVjReward::run($project->id);

        assertDatabaseHas('employee_rewards', [
            'employee_id' => $pm->id,
            'project_id' => $project->id,
            'role' => 'pm',
            'base_reward' => 1000000,
            'total_reward' => 1000000,
            'total_point' => 0,
            'point' => 0,
            'project_class_name' => 'Class B',
        ]);
    });

    it('splits two PMs 70/30 with the flagged lead taking 70', function () {
        $class = pmvjClass(['pm_reward' => 1000000]);
        $project = Project::factory()->create(['project_class_id' => $class->id]);
        $lead = Employee::factory()->create();
        $support = Employee::factory()->create();
        pmvjAddPic($project, $support, isLead: false);
        pmvjAddPic($project, $lead, isLead: true); // flagged lead even though added second

        RecordPmVjReward::run($project->id);

        assertDatabaseHas('employee_rewards', ['employee_id' => $lead->id, 'role' => 'pm', 'total_reward' => 700000]);
        assertDatabaseHas('employee_rewards', ['employee_id' => $support->id, 'role' => 'pm', 'total_reward' => 300000]);

        $paid = (float) EmployeeReward::where('project_id', $project->id)->where('role', 'pm')->sum('total_reward');
        expect($paid)->toBe(1000000.0);
    });

    it('splits three PMs 50/25/25', function () {
        $class = pmvjClass(['pm_reward' => 1000000]);
        $project = Project::factory()->create(['project_class_id' => $class->id]);
        $lead = Employee::factory()->create();
        $s1 = Employee::factory()->create();
        $s2 = Employee::factory()->create();
        pmvjAddPic($project, $lead, isLead: true);
        pmvjAddPic($project, $s1);
        pmvjAddPic($project, $s2);

        RecordPmVjReward::run($project->id);

        assertDatabaseHas('employee_rewards', ['employee_id' => $lead->id, 'role' => 'pm', 'total_reward' => 500000]);
        assertDatabaseHas('employee_rewards', ['employee_id' => $s1->id, 'role' => 'pm', 'total_reward' => 250000]);
        assertDatabaseHas('employee_rewards', ['employee_id' => $s2->id, 'role' => 'pm', 'total_reward' => 250000]);

        $paid = (float) EmployeeReward::where('project_id', $project->id)->where('role', 'pm')->sum('total_reward');
        expect($paid)->toBe(1000000.0);
    });

    it('falls back to the earliest-assigned PIC as lead when none is flagged', function () {
        $class = pmvjClass(['pm_reward' => 1000000]);
        $project = Project::factory()->create(['project_class_id' => $class->id]);
        $first = Employee::factory()->create();
        $second = Employee::factory()->create();
        pmvjAddPic($project, $first);  // no flag -> earliest becomes lead
        pmvjAddPic($project, $second);

        RecordPmVjReward::run($project->id);

        // first (lowest PIC id) is treated as lead -> 70%
        assertDatabaseHas('employee_rewards', ['employee_id' => $first->id, 'role' => 'pm', 'total_reward' => 700000]);
        assertDatabaseHas('employee_rewards', ['employee_id' => $second->id, 'role' => 'pm', 'total_reward' => 300000]);
    });

    it('absorbs rounding on the last PM row so an indivisible pot still sums exactly', function () {
        $class = pmvjClass(['pm_reward' => 10]); // 50/25/25 of 10 is not divisible into whole rupiah
        $project = Project::factory()->create(['project_class_id' => $class->id]);
        $lead = Employee::factory()->create();
        $s1 = Employee::factory()->create();
        $s2 = Employee::factory()->create();
        pmvjAddPic($project, $lead, isLead: true);
        pmvjAddPic($project, $s1);
        pmvjAddPic($project, $s2);

        RecordPmVjReward::run($project->id);

        $rewards = EmployeeReward::where('project_id', $project->id)->where('role', 'pm')->pluck('total_reward');
        $rewards->each(fn ($amount) => expect(fmod((float) $amount, 1))->toBe(0.0)); // whole rupiah
        expect((float) $rewards->sum())->toBe(10.0); // exact pot
    });

    it('records no PM reward when the class has no PM pot', function () {
        $class = pmvjClass(['pm_reward' => 0]);
        $project = Project::factory()->create(['project_class_id' => $class->id]);
        $pm = Employee::factory()->create();
        pmvjAddPic($project, $pm, isLead: true);

        RecordPmVjReward::run($project->id);

        expect(EmployeeReward::where('project_id', $project->id)->where('role', 'pm')->count())->toBe(0);
    });
});

describe('RecordPmVjReward - VJ reward', function () {
    it('pays every VJ the full class VJ amount', function () {
        $class = pmvjClass(['vj_reward' => 125000]);
        $project = Project::factory()->create(['project_class_id' => $class->id]);
        $vjA = Employee::factory()->create();
        $vjB = Employee::factory()->create();
        pmvjAddVj($project, $vjA);
        pmvjAddVj($project, $vjB);

        RecordPmVjReward::run($project->id);

        // per-VJ fixed: each earns the full amount (NOT split)
        assertDatabaseHas('employee_rewards', ['employee_id' => $vjA->id, 'role' => 'vj', 'base_reward' => 125000, 'total_reward' => 125000]);
        assertDatabaseHas('employee_rewards', ['employee_id' => $vjB->id, 'role' => 'vj', 'base_reward' => 125000, 'total_reward' => 125000]);

        $paid = (float) EmployeeReward::where('project_id', $project->id)->where('role', 'vj')->sum('total_reward');
        expect($paid)->toBe(250000.0); // 125k x 2 VJs
    });

    it('records PM and VJ rewards together, tagged by role', function () {
        $class = pmvjClass(['pm_reward' => 1000000, 'vj_reward' => 125000]);
        $project = Project::factory()->create(['project_class_id' => $class->id]);
        $pm = Employee::factory()->create();
        $vj = Employee::factory()->create();
        pmvjAddPic($project, $pm, isLead: true);
        pmvjAddVj($project, $vj);

        RecordPmVjReward::run($project->id);

        expect(EmployeeReward::where('project_id', $project->id)->where('role', 'pm')->count())->toBe(1)
            ->and(EmployeeReward::where('project_id', $project->id)->where('role', 'vj')->count())->toBe(1);
    });
});
