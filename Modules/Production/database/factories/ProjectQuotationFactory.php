<?php

namespace Modules\Production\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Production\Models\ProjectQuotation;
use Modules\Production\Models\QuotationItem;

class ProjectQuotationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Production\Models\ProjectQuotation::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'main_ballroom' => fake()->randomFloat(0,50000000, 200000000),
            'prefunction' => fake()->randomFloat(0,50000000, 150000000),
            'high_season_fee' => fake()->randomFloat(0,5000000, 7000000),
            'equipment_fee' => fake()->randomFloat(0, 2500000, 3000000),
            'sub_total' => fake()->randomFloat(0,50000000, 200000000),
            'maximum_discount' => fake()->randomFloat(0, 5000000, 10000000),
            'total' => fake()->randomFloat(0,50000000, 200000000),
            'maximum_markup_price' => fake()->randomFloat(0,50000000, 200000000),
            'event_location_guide' => fake()->randomElement(['jawa', 'luar_jawa', 'surabaya']),
            'fix_price' => fake()->randomFloat(0,50000000, 200000000),
            'quotation_id' => fake()->firstName(),
            'is_final' => 0,
        ];
    }

    public function withItems(int $count = 3, string $name = '')
    {
        return $this->afterCreating(function (ProjectQuotation $projectQuotation) use ($count, $name) {
            if ($count == 0) {
                $items = QuotationItem::factory()->count(1)->create([
                    'name' => $name ?: fake()->name()
                ]);
            } else {
                $items = QuotationItem::factory()->count(3)->create();
            }

            $projectQuotation->items()->createMany(
                collect($items)->map(function ($itemData) {
                    return [
                        'item_id' => $itemData->id
                    ];
                })->toArray()
            );
        });
    }
}

