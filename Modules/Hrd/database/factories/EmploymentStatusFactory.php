<?php

namespace Modules\Hrd\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class EmploymentStatusFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Hrd\Models\EmploymentStatus::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'code' => fake()->randomLetter() . fake()->randomLetter() . fake()->randomNumber(2),
            'name' => fake()->firstName(),
            'is_active' => 1,
            'is_terminal' => 0
        ];
    }
}

