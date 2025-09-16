<?php

namespace Modules\Finance\Database\Seeders;

use Illuminate\Database\Seeder;

class PriceChangeReasonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            ['name' => 'Market Price Adjustment'],
            ['name' => 'Cost Increase'],
            ['name' => 'Client Request'],
            ['name' => 'Project Scope Change'],
            ['name' => 'Currency Fluctuation'],
        ];

        foreach ($data as $item) {
            \Modules\Finance\Models\PriceChangeReason::updateOrCreate(
                ['name' => $item['name']],
                $item
            );
        }
    }
}
