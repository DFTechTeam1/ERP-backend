<?php

namespace Modules\Production\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectTask;

class ProjectTaskDurationHistoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Production\Models\ProjectTaskDurationHistory::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'task_id' => ProjectTask::factory(),
            'pic_id' => Employee::factory(),
            'employee_id' => Employee::factory(),
            'task_type' => $this->faker->word,

            // in second
            'task_full_duration' => $this->faker->numberBetween(1, 100),
            'task_holded_duration' => $this->faker->numberBetween(1, 100),
            'task_revised_duration' => $this->faker->numberBetween(1, 100),
            'task_actual_duration' => $this->faker->numberBetween(1, 100),
            'task_approval_duration' => $this->faker->numberBetween(1, 100),

            // in number
            'total_task_holded' => $this->faker->numberBetween(1, 100),
            'total_task_revised' => $this->faker->numberBetween(1, 100),
        ];
    }
}
