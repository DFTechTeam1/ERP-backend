<?php

namespace Modules\Production\Database\Factories;

use App\Enums\Production\TaskStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

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
            'task_identifier_id' => fake()->word(),
            'project_id' => null,
            'project_board_id' => 1,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(2)->format('Y-m-d'),
            'description' => null,
            'name' => 'My Task',
            'start_working_at' => null,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
            'task_type' => null,
            'status' => TaskStatus::OnProgress->value,
            'current_pics' => null,
            'current_board' => null,
            'is_approved' => false,
            'is_modeler_task' => false,
        ];
    }
}

