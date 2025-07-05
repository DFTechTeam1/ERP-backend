<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Company\Models\Country;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->country(),
            'iso3' => fake()->countryISOAlpha3(),
            'iso2' => fake()->countryISOAlpha3(),
            'phone_code' => fake()->e164PhoneNumber(),
            'currency' => fake()->currencyCode()
        ];
    }
}
