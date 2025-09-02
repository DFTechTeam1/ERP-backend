<?php

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceRequestUpdateFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Finance\Models\InvoiceRequestUpdate::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'amount' => 10000000,
            'payment_date' => now()->addDays(30)->format('Y-m-d'),
            'status' => \App\Enums\Finance\InvoiceRequestUpdateStatus::Pending->value,
            'invoice_id' => \Modules\Finance\Models\Invoice::factory(),
            'request_by' => \App\Models\User::factory(),
        ];
    }
}
