<?php

namespace Modules\Hrd\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class GreatdayCostCenterFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Hrd\Models\GreatdayCostCenter::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name_en' => fake()->firstName(),
            'name_id' => fake()->lastName(),
            'code' => fake()->word()
        ];
    }
}

