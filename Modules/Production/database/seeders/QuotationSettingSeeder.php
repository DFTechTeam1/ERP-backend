<?php

namespace Modules\Production\Database\Seeders;

use Illuminate\Database\Seeder;

class QuotationSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $payload = [
            [
                'key' => 'quotation_prefix',
                'value' => '#DF',
                'code' => 'company',
            ],
            [
                'key' => 'cutoff_quotation_number',
                'value' => '4101',
                'code' => 'company',
            ],
        ];

        foreach ($payload as $data) {
            $key = $data['key'];
            \Modules\Company\Models\Setting::whereRaw("`key` = '{$key}'")
                ->delete();

            \Modules\Company\Models\Setting::create($data);
        }

        \Illuminate\Support\Facades\Cache::forget('setting');
    }
}
