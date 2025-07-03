<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Company\Models\City;
use Modules\Company\Models\Country;
use Modules\Company\Models\State;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class CityFactory extends Factory
{
    protected $model = City::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $country = Country::factory()
            ->has(State::factory()->count(2))
            ->create();

        return [
            'country_id' => $country->id,
            'state_id' => $country->states[0]->id,
            'name' => fake()->city(),
            'country_code' => $country->iso3
        ];
    }
}
