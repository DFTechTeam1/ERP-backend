<?php

namespace Modules\Company\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Company\Models\IndonesiaDistrict;

class VillageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Company\Models\IndonesiaVillage::class;

    protected static $sequence = 1;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'code' => self::$sequence++,
            'district_code' => IndonesiaDistrict::factory()->create()->code,
            'name' => fake()->randomElement(['BANDULAN', 'SUKUN', 'BLIMBING', 'KLOJEN', 'TAMBAKSARI']),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'postal_code' => fake()->randomNumber(4),
        ];
    }
}

