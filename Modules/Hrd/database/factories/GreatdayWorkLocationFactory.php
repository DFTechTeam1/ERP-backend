<?php

namespace Modules\Hrd\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class GreatdayWorkLocationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Hrd\Models\GreatdayWorkLocation::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'code' => fake()->word(),
            'address' => fake()->address(),
            'max_radius' => 0.02
        ];
    }
}

