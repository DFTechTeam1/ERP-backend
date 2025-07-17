<?php

namespace Modules\Production\Database\Factories;

use App\Enums\Production\TaskPicStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Production\Models\ProjectTask;

class ProjectTaskPicFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Production\Models\ProjectTaskPic::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'project_task_id' => ProjectTask::factory(),
            'employee_id' => null,
            'status' => TaskPicStatus::Approved->value,
            'approved_at' => now()->format('Y-m-d H:i:s'),
            'assigned_at' => now()->subDay()->format('Y-m-d H:i:s'),
        ];
    }
}

