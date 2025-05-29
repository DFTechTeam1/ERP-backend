<?php

namespace Modules\Company\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Company\Models\Setting;

class CompanySettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $payload = [
            [
                'code' => 'company',
                'key' => 'company_name',
                'value' => 'DFactory'
            ],
            [
                'code' => 'company',
                'key' => 'company_phone',
                'value' => '+(62) 821 1068 6655'
            ],
            [
                'code' => 'company',
                'key' => 'company_email',
                'value' => 'dfactory.id@gmail.com'
            ],
            [
                'code' => 'company',
                'key' => 'company_address',
                'value' => 'Kaca Piring 19 / 2nd level Surabaya - East Java'
            ],
        ];

        foreach ($payload as $data) {
            Setting::where('key', $data['key'])->delete();

            Setting::create($data);
        }
    }
}
