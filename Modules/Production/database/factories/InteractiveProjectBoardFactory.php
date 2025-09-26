<?php

namespace Modules\Production\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Production\Models\InteractiveProject;

class InteractiveProjectBoardFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Production\Models\InteractiveProjectBoard::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'project_id' => InteractiveProject::factory(),
            'name' => fake()->sentence(1),
            'sort' => fake()->randomNumber(1),
        ];
    }
}
