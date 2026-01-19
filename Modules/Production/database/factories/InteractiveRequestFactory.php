<?php

namespace Modules\Production\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Production\Models\ProjectDeal;

class InteractiveRequestFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Production\Models\InteractiveRequest::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'project_deal_id' => ProjectDeal::factory(),
            'requester_id' => \App\Models\User::factory(),
            'status' => \App\Enums\Interactive\InteractiveRequestStatus::Pending->value,
            'interactive_detail' => [
                [
                    'name' => 'main',
                    'textDetail' => '5 x 6 m',
                    'total' => '30 m<sup>2</sup>',
                    'totalRaw' => '30',
                    'led' => [
                        [
                            'height' => '6',
                            'width' => '5'
                        ]
                    ]
                ]
            ],
            'interactive_area' => '20',
            'interactive_note' => $this->faker->text(),
            'interactive_fee' => $this->faker->randomFloat(2, 0, 1000),
            'fix_price' => $this->faker->randomFloat(2, 0, 1000),
            'approved_at' => null,
            'approved_by' => null,
            'rejected_at' => null,
            'rejected_by' => null,
        ];
    }
}

