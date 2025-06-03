<?php

namespace Modules\Company\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Company\Models\Division;

class PositionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Company\Models\Position::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Lead Project Manager', 'Project Manager', 'HRD', 'Fullstack Developer', 'AI Engineer']),
            'division_id' => Division::factory(),
        ];
    }
}
