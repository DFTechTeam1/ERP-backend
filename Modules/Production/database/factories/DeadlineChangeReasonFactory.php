<?php

namespace Modules\Production\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DeadlineChangeReasonFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Production\Models\DeadlineChangeReason::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
        ];
    }
}