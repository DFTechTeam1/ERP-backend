<?php

namespace Modules\Production\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Company\Models\Setting;

class PriceSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $payload = [
            // discount
            [
                'code' => 'price',
                'key' => 'discount_type',
                'value' => 'percentage',
            ],
            [
                'code' => 'price',
                'key' => 'discount',
                'value' => '10',
            ],

            // markup price
            [
                'code' => 'price',
                'key' => 'markup_type',
                'value' => 'percentage',
            ],
            [
                'code' => 'price',
                'key' => 'markup',
                'value' => '10',
            ],

            // high season
            [
                'code' => 'price',
                'key' => 'high_season_type',
                'value' => 'percentage',
            ],
            [
                'code' => 'price',
                'key' => 'high_season',
                'value' => '25',
            ],

            // equipment
            [
                'code' => 'price',
                'key' => 'equipment_type',
                'value' => 'fix',
            ],
            [
                'code' => 'price',
                'key' => 'equipment',
                'value' => '2500000',
            ],

            // price guide area
            [
                'code' => 'price_guide',
                'key' => 'Surabaya',
                'value' => 'surabaya',
            ],
            [
                'code' => 'price_guide',
                'key' => 'Jakarta',
                'value' => 'jakarta',
            ],
            [
                'code' => 'price_guide',
                'key' => 'Jawa',
                'value' => 'jawa',
            ],
            [
                'code' => 'price_guide',
                'key' => 'Luar Jawa',
                'value' => 'luar jawa',
            ],
        ];

        foreach ($payload as $data) {
            Setting::where('key', $data['key'])->delete();

            Setting::create($data);
        }

        // setting area guide price

    }
}
