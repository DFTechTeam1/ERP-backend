<?php

namespace Modules\Production\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\ProjectTask;

class ProjectTaskDeadlineFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Production\Models\ProjectTaskDeadline::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'project_task_id' => ProjectTask::factory(),
            'employee_id' => Employee::factory(),
            'deadline' => now()->addWeeks(3)->format('Y-m-d H:i:s'),
            'is_first_deadline' => true,
            'due_reason' => null,
            'updated_by' => User::factory(),
        ];
    }
}
