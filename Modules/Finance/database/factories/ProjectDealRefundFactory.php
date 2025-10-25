<?php

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Production\Models\ProjectDeal;

class ProjectDealRefundFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Finance\Models\ProjectDealRefund::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'project_deal_id' => ProjectDeal::factory()->withQuotation(1000000),
            'refund_amount' => $this->faker->numberBetween(10000, 500000),
            'refund_percentage' => 0,
            'refund_reason' => $this->faker->sentence,
            'status' => \App\Enums\Finance\RefundStatus::Pending->value,
            'refund_type' => 'fixed',
            'created_by' => \App\Models\User::factory(),
        ];
    }
}

