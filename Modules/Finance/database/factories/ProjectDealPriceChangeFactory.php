<?php

namespace Modules\Finance\Database\Factories;

use App\Enums\Production\ProjectDealChangePriceStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Production\Models\ProjectDeal;

class ProjectDealPriceChangeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Finance\Models\ProjectDealPriceChange::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'project_deal_id' => ProjectDeal::factory(),
            'old_price' => $this->faker->randomFloat(2, 1000, 100000),
            'new_price' => $this->faker->randomFloat(2, 1000, 100000),
            'reason_id' => \Modules\Finance\Models\PriceChangeReason::factory(),
            'custom_reason' => null,
            'requested_by' => User::factory(),
            'requested_at' => now(),
            'approved_by' => null,
            'approved_at' => null,
            'rejected_at' => null,
            'rejected_by' => null,
            'rejected_reason' => null,
            'status' => ProjectDealChangePriceStatus::Pending->value,
        ];
    }
}

