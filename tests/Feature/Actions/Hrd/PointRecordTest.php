<?php

namespace Tests\Feature\Actions\Hrd;

use App\Actions\Hrd\PointRecord;
use App\Traits\TestUserAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\Project;
use Tests\TestCase;

class PointRecordTest extends TestCase
{
    use RefreshDatabase, TestUserAuthentication;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * A basic feature test example.
     */
    public function test_point_record_return_success(): void
    {
        $projects = Project::factory()
            ->count(1)
            ->create();

        $employees = Employee::factory()
            ->count(4)
            ->create();

        $payload = [];
        foreach ($employees as $key => $employee) {
            $tasks = [1];
            if ($key == 1) {
                $tasks = [2, 3];
            } elseif ($key == 2) {
                $tasks = [5];
            } elseif ($key == 3) {
                $tasks = [10, 3];
            }

            $payload['points'][] = [
                'uid' => $employee->uid,
                'point' => count($tasks) + 1,
                'additional_point' => 1,
                'tasks' => $tasks,
            ];
        }

        $point = PointRecord::run($payload, $projects[0]->uid, 'production');

        $this->assertTrue($point);

        // check database
        foreach ($employees as $keyEmployee => $employee) {
            $this->assertDatabaseHas('employee_points', ['employee_id' => $employee->id, 'total_point' => $payload['points'][$keyEmployee]['point']]);

            foreach ($payload['points'][$keyEmployee]['tasks'] as $task) {
                $this->assertDatabaseHas('employee_point_project_details', ['task_id' => $task]);
            }
        }
    }

    public function test_production_with_multiple_task_in_one_project(): void
    {
        $projects = Project::factory()
            ->count(2)
            ->create();

        $employees = Employee::factory()
            ->count(1)
            ->create();

        $payload = [
            'points' => [
                [
                    'uid' => $employees[0]->uid,
                    'point' => 1,
                    'additional_point' => 0,
                    'tasks' => [25],
                ],
            ],
        ];

        PointRecord::run($payload, $projects[0]->uid, 'production');

        // create another point
        $payload['points'][0]['point'] = 3;
        $payload['points'][0]['additional_point'] = 1;
        $payload['points'][0]['tasks'] = [30, 33];

        PointRecord::run($payload, $projects[1]->uid, 'production');

        $this->assertDatabaseHas('employee_points', ['employee_id' => $employees[0]->id, 'total_point' => 4]);

        $this->assertDatabaseHas('employee_point_projects', ['project_id' => $projects[0]->id]);

        $this->assertDatabaseHas('employee_point_project_details', ['task_id' => 25]);
    }

    public function test_entertaiment_with_multiple_task_in_one_project(): void
    {
        $projects = Project::factory()
            ->count(1)
            ->create();

        $employees = Employee::factory()
            ->count(1)
            ->create();

        $payload = [
            'points' => [
                [
                    'uid' => $employees[0]->uid,
                    'point' => 1,
                    'additional_point' => 0,
                    'tasks' => [45],
                ],
            ],
        ];

        PointRecord::run($payload, $projects[0]->uid, 'entertainment');

        // create another point
        $payload['points'][0]['point'] = 1;
        $payload['points'][0]['additional_point'] = 0;
        $payload['points'][0]['tasks'] = [46];

        PointRecord::run($payload, $projects[0]->uid, 'entertainment');

        $this->assertDatabaseHas('employee_points', ['employee_id' => $employees[0]->id, 'total_point' => 1]);

        $this->assertDatabaseHas('employee_point_project_details', ['task_id' => 46]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
