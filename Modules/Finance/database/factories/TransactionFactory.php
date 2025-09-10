<?php

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Production\Models\Customer;
use Modules\Production\Models\ProjectDeal;

class TransactionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Finance\Models\Transaction::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'project_deal_id' => ProjectDeal::factory(),
            'customer_id' => Customer::factory(),
            'payment_amount' => 100000000,
            'reference' => null,
            'note' => null,
            'trx_id' => fake()->randomNumber(),
            'transaction_date' => '2025-10-10',
        ];
    }
}
