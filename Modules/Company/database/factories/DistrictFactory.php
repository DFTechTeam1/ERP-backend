<?php

namespace Modules\Company\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Company\Models\IndonesiaCity;
use Modules\Company\Models\IndonesiaDistrict;

class DistrictFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = IndonesiaDistrict::class;

    protected static $sequence = 1;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'code' => self::$sequence++,
            'city_code' => IndonesiaCity::factory()->create()->code,
            'name' => fake()->randomElement(['MALANG', 'PASURUAN', 'BLITAR', 'SURABAYA', 'BATU']),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
        ];
    }
}
