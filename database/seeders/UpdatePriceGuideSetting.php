<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Company\Models\Setting;

class UpdatePriceGuideSetting extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currentAreaGuidePrice = Setting::where('key', 'area_guide_price')->first();
        if ($currentAreaGuidePrice) {
            $currentAreaGuidePrice = json_decode($currentAreaGuidePrice->value, true);

            if (isset($currentAreaGuidePrice['area'])) {
                $interactiveKey = collect($currentAreaGuidePrice['area'])->filter(function ($item) {
                    return $item['area'] === 'Interactive';
                })->first();

                if (! $interactiveKey) {
                    $currentAreaGuidePrice['area'][] = [
                        'area' => 'Interactive',
                        'settings' => [
                            [
                                'name' => 'Main Ballroom Fee',
                                'type' => 'fixed',
                                'value' => 1250000,
                                'fixType' => 'fixed',
                            ],
                            [
                                'name' => 'Prefunction Fee',
                                'type' => 'fixed',
                                'value' => 1250000,
                                'fixType' => 'fixed',
                            ],
                            [
                                'name' => 'Max Discount',
                                'type' => 'percentage',
                                'value' => 10,
                                'fixType' => 'flexible',
                            ],
                        ],
                    ];

                    Setting::updateOrCreate(
                        ['key' => 'area_guide_price'],
                        ['value' => json_encode($currentAreaGuidePrice)]
                    );
                }
            }
        }
    }
}
