<?php

namespace Modules\Company\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BranchFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Company\Models\Branch::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['PT. Citra Bahagia Indonesia', 'Dfactory']),
            'short_name' => fake()->unique()->randomElement(['CBI', 'DF'])
        ];
    }
}

