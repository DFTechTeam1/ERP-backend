<?php

namespace Modules\Company\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Company\Models\ProjectClass;

class ProjectClassFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = ProjectClass::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'maximal_point' => fake()->numberBetween(1, 50),
            // color is required by the Create/Update requests and non-nullable in ListClassData,
            // so always produce one; reward pots default to 0 like the DB columns.
            'color' => fake()->hexColor(),
            'reward' => 0,
            'pm_reward' => 0,
            'vj_reward' => 0,
            'is_active' => true,
        ];
    }
}
