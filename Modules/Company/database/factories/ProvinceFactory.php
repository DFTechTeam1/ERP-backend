<?php

namespace Modules\Company\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProvinceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Company\Models\Province::class;

    public static $sequence = 1;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $code = self::$sequence++;

        return [
            'name' => fake()->randomElement(['ACEH', 'SUMATERA UTARA', 'JAWA TIMUR', 'JAWA TENGAH', 'JAWA BARAT']),
            'code' => $code,
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
        ];
    }
}
