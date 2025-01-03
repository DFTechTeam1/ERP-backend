<?php

namespace Modules\Production\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Company\Models\ProjectClass;

class EventTypeFactory extends Factory
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
            'name' => 'A (Besar)',
            'maximal_point' => 20,
            'color' => 'black'
        ];
    }
}

