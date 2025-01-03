<?php

namespace Modules\Company\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Company\Models\IndonesiaCity;
use Modules\Company\Models\Province;

class CityFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = IndonesiaCity::class;

    protected static $sequence = 1;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'code' => self::$sequence++,
            'province_code' => Province::factory()->create()->code,
            'name' => fake()->randomElement(['MALANG', 'BATU', 'SURABAYA', 'GRESIK', 'TULUNGAGUNG', 'BLITAR']),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
        ];
    }
}

