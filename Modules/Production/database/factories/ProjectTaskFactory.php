<?php

namespace Modules\Production\Database\Factories;

use App\Enums\Development\Project\Task\TaskStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\Project;

class ProjectTaskFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Production\Models\ProjectTask::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $project = Project::factory()
            ->withBoards()
            ->create();
        return [
            'uid' => $this->faker->uuid(),
            'task_identifier_id' => $this->faker->uuid(),
            'project_id' => $project->id,
            'project_board_id' => $project->boards->first()->id,
            'start_date' => null,
            'end_date' => null,
            'description' => $this->faker->paragraph(),
            'name' => 'Task ok',
            'start_working_at' => null,
            'created_by' => null,
            'updated_by' => null,
            'task_type' => null,
            'performance_time' => null,
            'status' => TaskStatus::Completed->value,
            'current_pics' => null,
            'current_board' => null,
            'is_approved' => false,
            'is_modeler_task' => false,
        ];
    }

    public function withPics(?Employee $employee = null)
    {
        return $this->afterCreating(function (\Modules\Production\Models\ProjectTask $task) use ($employee) {
            if (!$employee) {
                $employee = Employee::factory()->create();
                $employeeId = $employee->id;
            } else {
                $employeeId = $employee->id;
            }

            \Modules\Production\Models\ProjectTaskPic::create([
                    'project_task_id' => $task->id,
                    'employee_id' => $employeeId,
                    'approved_at' => now(),
                    'assigned_at' => now(),
                    'status' => \App\Enums\Production\TaskPicStatus::Approved->value
                ]);
        });
    }
}
