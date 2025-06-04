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
                'key' => 'area_guide_price',
                'value' => json_encode([
                    'area' => [
                        [
                            'area' => 'Surabaya',
                            'settings' => [
                                [
                                    'name' => 'Main Ballroom Fee',
                                    'type' => 'fixed',
                                    'value' => 0,
                                ],
                                [
                                    'name' => 'Prefunction Fee',
                                    'type' => 'fixed',
                                    'value' => 0,
                                ],
                                [
                                    'name' => 'Max Discount',
                                    'type' => 'percentage',
                                    'value' => 10,
                                ],
                            ],
                        ],
                        [
                            'area' => 'Jakarta',
                            'settings' => [
                                [
                                    'name' => 'Main Ballroom Fee',
                                    'type' => 'fixed',
                                    'value' => 0,
                                ],
                                [
                                    'name' => 'Prefunction Fee',
                                    'type' => 'fixed',
                                    'value' => 0,
                                ],
                                [
                                    'name' => 'Max Discount',
                                    'type' => 'percentage',
                                    'value' => 10,
                                ],
                            ],
                        ],
                        [
                            'area' => 'Jawa',
                            'settings' => [
                                [
                                    'name' => 'Main Ballroom Fee',
                                    'type' => 'fixed',
                                    'value' => 0,
                                ],
                                [
                                    'name' => 'Prefunction Fee',
                                    'type' => 'fixed',
                                    'value' => 0,
                                ],
                                [
                                    'name' => 'Max Discount',
                                    'type' => 'percentage',
                                    'value' => 10,
                                ],
                            ],
                        ],
                        [
                            'area' => 'Luar Jawa',
                            'settings' => [
                                [
                                    'name' => 'Main Ballroom Fee',
                                    'type' => 'fixed',
                                    'value' => 0,
                                ],
                                [
                                    'name' => 'Prefunction Fee',
                                    'type' => 'fixed',
                                    'value' => 0,
                                ],
                                [
                                    'name' => 'Max Discount',
                                    'type' => 'percentage',
                                    'value' => 10,
                                ],
                            ],
                        ],
                    ],
                    'equipment' => [
                        [
                            'name' => 'Lasika',
                            'type' => 'fixed',
                            'value' => 0,
                        ],
                        [
                            'name' => 'Others',
                            'type' => 'fixed',
                            'value' => 2500000,
                        ],
                    ],
                    'price_up' => [
                        'type' => 'percentage',
                        'value' => 11,
                    ],
                ]),
            ],
        ];

        foreach ($payload as $data) {
            Setting::where('key', $data['key'])->delete();

            Setting::create($data);
        }

        // setting area guide price

    }
}
