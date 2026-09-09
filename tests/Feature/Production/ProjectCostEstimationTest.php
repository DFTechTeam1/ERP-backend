<?php

use App\Models\User;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmployeePoint;
use Modules\Hrd\Models\EmployeePointProject;
use Modules\Hrd\Models\EmployeeReward;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectDeal;
use Modules\Production\Models\ProjectQuotation;
use Modules\Production\Services\ProjectService;

use function Pest\Laravel\actingAs;

/**
 * ProjectService::getProjectCostEstimation() and its endpoint
 * GET /api/production/cost-estimation (ProjectController@getProjectCostEstimation).
 *
 * The method assembles a CostEstimationListData: the project's price (from the linked deal's
 * final quotation, else 0) and the per-employee rewards (id/name/avatar/total_point/total_reward).
 */
function costService(): ProjectService
{
    return app(ProjectService::class);
}

/**
 * Create a project linked to a deal whose final quotation carries $fixPrice.
 */
function costProjectWithPrice(float $fixPrice, array $projectAttributes = []): Project
{
    $deal = ProjectDeal::factory()->create();
    ProjectQuotation::factory()->create([
        'project_deal_id' => $deal->id,
        'is_final' => 1,
        'fix_price' => $fixPrice,
    ]);

    return Project::factory()->create(array_merge(['project_deal_id' => $deal->id], $projectAttributes));
}

/**
 * Attach one employee reward to $project and return the employee.
 */
function costAddReward(Project $project, array $employeeAttributes, int $totalPoint, float $totalReward): Employee
{
    $employee = Employee::factory()->create(array_merge(['avatar' => 'avatar.png'], $employeeAttributes));

    $point = EmployeePoint::create(['employee_id' => $employee->id, 'total_point' => 0, 'type' => 'production']);
    $pointProject = EmployeePointProject::create([
        'employee_point_id' => $point->id,
        'project_id' => $project->id,
        'total_point' => $totalPoint,
        'additional_point' => 0,
        'prorate_point' => 0,
        'calculated_prorate_point' => 0,
        'original_point' => $totalPoint,
    ]);
    EmployeeReward::create([
        'employee_id' => $employee->id,
        'project_id' => $project->id,
        'employee_point_project_id' => $pointProject->id,
        'base_reward' => 50000,
        'total_point' => $totalPoint,
        'point' => $totalPoint,
        'additional_point' => 0,
        'total_reward' => $totalReward,
        'project_class_name' => 'A',
    ]);

    return $employee;
}

describe('getProjectCostEstimation (service)', function () {
    it('returns the project price from the final quotation and the employee rewards', function () {
        $project = costProjectWithPrice(150000000, ['name' => 'Big Show', 'venue' => 'Grand Hall']);
        costAddReward($project, ['name' => 'Budi Santoso'], totalPoint: 5, totalReward: 250000);

        $response = costService()->getProjectCostEstimation($project->uid);

        expect($response['error'])->toBeFalse();

        $data = $response['data'];
        expect($data['project_id'])->toBe($project->uid)
            ->and($data['project_name'])->toBe('Big Show')
            ->and($data['venue'])->toBe('Grand Hall')
            ->and((float) $data['project_price'])->toBe(150000000.0)
            ->and($data['total_employees'])->toBe(0)
            ->and($data['meal_allowances'])->toBe([])
            ->and($data['transport_allowances'])->toBe([])
            ->and($data['employee_rewards'])->toHaveCount(1);

        $reward = $data['employee_rewards'][0];
        expect($reward['name'])->toBe('Budi Santoso')
            ->and($reward['avatar'])->toBe('avatar.png')
            ->and($reward['total_point'])->toBe(5)
            ->and((float) $reward['total_reward'])->toBe(250000.0);
    });

    it('lists a reward per rewarded employee', function () {
        $project = costProjectWithPrice(100000000);
        costAddReward($project, ['name' => 'Employee A'], 3, 90000);
        costAddReward($project, ['name' => 'Employee B'], 7, 210000);

        $response = costService()->getProjectCostEstimation($project->uid);

        $names = collect($response['data']['employee_rewards'])->pluck('name');
        expect($response['data']['employee_rewards'])->toHaveCount(2)
            ->and($names)->toContain('Employee A')
            ->and($names)->toContain('Employee B');
    });

    it('returns a price of 0 when the project has no linked deal', function () {
        $project = Project::factory()->create(['project_deal_id' => null]);

        $response = costService()->getProjectCostEstimation($project->uid);

        expect($response['error'])->toBeFalse()
            ->and((float) $response['data']['project_price'])->toBe(0.0);
    });

    it('returns a price of 0 when the linked deal has no final quotation', function () {
        $deal = ProjectDeal::factory()->create(); // no final quotation
        $project = Project::factory()->create(['project_deal_id' => $deal->id]);

        $response = costService()->getProjectCostEstimation($project->uid);

        expect($response['error'])->toBeFalse()
            ->and((float) $response['data']['project_price'])->toBe(0.0);
    });

    it('returns an empty reward list when the project has no rewards', function () {
        $project = costProjectWithPrice(50000000);

        $response = costService()->getProjectCostEstimation($project->uid);

        expect($response['error'])->toBeFalse()
            ->and($response['data']['employee_rewards'])->toBe([]);
    });

    it('returns an error response for a non-existent project', function () {
        $response = costService()->getProjectCostEstimation('non-existent-uid');

        expect($response['error'])->toBeTrue();
    });
});

describe('GET /api/production/cost-estimation (e2e)', function () {
    beforeEach(function () {
        actingAs(User::factory()->create());
    });

    it('returns the cost estimation for the given project', function () {
        $project = costProjectWithPrice(120000000, ['name' => 'Gala Night']);
        costAddReward($project, ['name' => 'Dewi'], 4, 120000);

        $response = $this->getJson('/api/production/cost-estimation/'.$project->uid);

        $response->assertStatus(201)
            ->assertJsonPath('data.project_name', 'Gala Night')
            ->assertJsonPath('data.employee_rewards.0.name', 'Dewi');
    });

    it('returns an error for a non-existent project', function () {
        $this->getJson('/api/production/cost-estimation/non-existent-uid')
            ->assertStatus(400);
    });
});
