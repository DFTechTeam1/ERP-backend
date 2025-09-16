<?php

namespace Modules\Company\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExportImportResultFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Company\Models\ExportImportResult::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'description' => fake()->text(150),
            'message' => fake()->text(200),
            'area' => 'finance',
        ];
    }
}
