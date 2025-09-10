<?php

namespace Modules\Production\Database\Factories;

use App\Enums\Development\Project\Task\TaskStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
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
        return [
            'uid' => $this->faker->uuid(),
            'task_identifier_id' => $this->faker->uuid(),
            'project_id' => Project::factory(),
            'project_board_id' => 1,
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
            'is_approved' => null,
            'is_modeler_task' => null,
        ];
    }
}
