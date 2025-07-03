<?php

namespace Modules\Finance\Database\Factories;

use App\Enums\Transaction\InvoiceStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Production\Models\Customer;
use Modules\Production\Models\ProjectDeal;

class InvoiceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Finance\Models\Invoice::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'amount' => 100000000,
            'paid_amount' => 0,
            'payment_date' => now()->format('Y-m-d'),
            'payment_due' => now()->addDays(7)->format('Y-m-d'),
            'project_deal_id' => ProjectDeal::factory(),
            'customer_id' => Customer::factory(),
            'status' => fake()->randomElement([InvoiceStatus::Unpaid->value, InvoiceStatus::Paid->value]),
            'raw_data' => null,
            
            // numbering
            'parent_number' => null,
            'number' => 'IV/2025 - 950',
            'is_main' => 1,
            'sequence' => 0,

            'created_by' => User::factory()
        ];
    }
}

