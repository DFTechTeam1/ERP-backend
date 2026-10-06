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

function pmvjAddTier(ProjectClass $class, int $pmCount, float $pmReward, float $productionReward = 0, float $leadReward = 0, float $supportReward = 0)
{
    return $class->tiers()->create([
        'pm_count' => $pmCount,
        'pm_reward' => $pmReward,
        'production_reward' => $productionReward,
        'lead_reward' => $leadReward,
        'support_reward' => $supportReward,
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

describe('RecordPmVjReward - tiered PM pot', function () {
    // Class S tariff: each tier sets explicit per-role amounts (lead_reward / support_reward).
    //   1 PM -> lead 2,500,000
    //   2 PM -> lead 1,750,000 / support   750,000   (pot 2,500,000)
    //   3 PM -> lead 1,500,000 / support   750,000 x2 (pot 3,000,000)
    it('pays the tier lead_reward to the Lead and support_reward to each Support (2 PMs)', function () {
        $class = pmvjClass(['name' => 'Class S', 'pm_reward' => 9999999]); // flat pot must be ignored
        // pmvjAddTier(class, pmCount, pmReward, productionReward, leadReward, supportReward)
        pmvjAddTier($class, 1, 2500000, 0, 2500000, 0);
        pmvjAddTier($class, 2, 2500000, 0, 1750000, 750000);
        pmvjAddTier($class, 3, 3000000, 0, 1500000, 750000);

        $project = Project::factory()->create(['project_class_id' => $class->id]);
        $lead = Employee::factory()->create();
        $support = Employee::factory()->create();
        pmvjAddPic($project, $lead, isLead: true);
        pmvjAddPic($project, $support);

        RecordPmVjReward::run($project->id);

        // 2-PM tier: Lead = lead_reward, Support = support_reward; base_reward = the tier pot.
        assertDatabaseHas('employee_rewards', ['employee_id' => $lead->id, 'role' => 'pm', 'base_reward' => 2500000, 'total_reward' => 1750000]);
        assertDatabaseHas('employee_rewards', ['employee_id' => $support->id, 'role' => 'pm', 'base_reward' => 2500000, 'total_reward' => 750000]);
    });

    it('pays lead_reward once and support_reward per Support (3 PMs)', function () {
        $class = pmvjClass(['name' => 'Class S3', 'pm_reward' => 1]); // flat pot ignored
        pmvjAddTier($class, 1, 2500000, 0, 2500000, 0);
        pmvjAddTier($class, 2, 2500000, 0, 1750000, 750000);
        pmvjAddTier($class, 3, 3000000, 0, 1500000, 750000);

        $project = Project::factory()->create(['project_class_id' => $class->id]);
        $lead = Employee::factory()->create();
        $s1 = Employee::factory()->create();
        $s2 = Employee::factory()->create();
        pmvjAddPic($project, $lead, isLead: true);
        pmvjAddPic($project, $s1);
        pmvjAddPic($project, $s2);

        RecordPmVjReward::run($project->id);

        // 3-PM tier: lead 1,500,000; each of the two supports 750,000.
        assertDatabaseHas('employee_rewards', ['employee_id' => $lead->id, 'role' => 'pm', 'total_reward' => 1500000]);
        assertDatabaseHas('employee_rewards', ['employee_id' => $s1->id, 'role' => 'pm', 'total_reward' => 750000]);
        assertDatabaseHas('employee_rewards', ['employee_id' => $s2->id, 'role' => 'pm', 'total_reward' => 750000]);

        $paid = (float) EmployeeReward::where('project_id', $project->id)->where('role', 'pm')->sum('total_reward');
        expect($paid)->toBe(3000000.0); // 1.5M + 0.75M + 0.75M
    });

    it('falls back to the flat pm_reward pot split by headcount when no tier matches', function () {
        // Tiers cover 1-3 PMs; a 4-PM event has no matching tier, so the flat pm_reward pot is
        // split by headcount instead of using any tier's lead/support amounts.
        $class = pmvjClass(['name' => 'Class S4', 'pm_reward' => 1000000]);
        pmvjAddTier($class, 1, 2500000, 0, 2500000, 0);
        pmvjAddTier($class, 2, 2500000, 0, 1750000, 750000);
        pmvjAddTier($class, 3, 3000000, 0, 1500000, 750000);

        $project = Project::factory()->create(['project_class_id' => $class->id]);
        $pms = Employee::factory()->count(4)->create();
        $pms->each(fn ($pm, $i) => pmvjAddPic($project, $pm, isLead: $i === 0));

        RecordPmVjReward::run($project->id);

        // Base pot 1,000,000 split equally across 4 PMs (beyond the tariff) -> 250k each, exact sum.
        $rewards = EmployeeReward::where('project_id', $project->id)->where('role', 'pm')->pluck('total_reward');
        expect($rewards)->toHaveCount(4)
            ->and((float) $rewards->sum())->toBe(1000000.0);
        $rewards->each(fn ($amount) => expect((float) $amount)->toBe(250000.0));
    });

    it('keeps the VJ reward flat (not tiered) even when the class has tiers', function () {
        $class = pmvjClass(['name' => 'Class S VJ', 'vj_reward' => 350000]);
        pmvjAddTier($class, 1, 2500000);
        pmvjAddTier($class, 2, 2500000);

        $project = Project::factory()->create(['project_class_id' => $class->id]);
        $vjA = Employee::factory()->create();
        $vjB = Employee::factory()->create();
        pmvjAddVj($project, $vjA);
        pmvjAddVj($project, $vjB);
        // A PIC so personInCharges is non-empty (tier resolution reads that count).
        pmvjAddPic($project, Employee::factory()->create(), isLead: true);

        RecordPmVjReward::run($project->id);

        assertDatabaseHas('employee_rewards', ['employee_id' => $vjA->id, 'role' => 'vj', 'base_reward' => 350000, 'total_reward' => 350000]);
        assertDatabaseHas('employee_rewards', ['employee_id' => $vjB->id, 'role' => 'vj', 'base_reward' => 350000, 'total_reward' => 350000]);
    });
});
