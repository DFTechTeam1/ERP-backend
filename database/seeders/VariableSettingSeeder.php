<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Company\Models\Setting;

class VariableSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Setting::where('code', 'variables')
            ->delete();

        $data = [
            [
                'key' => 'position_as_directors',
                'value' => 'DF Data Center',
                'code' => 'variables',
            ],
            [
                'key' => 'position_as_marketing',
                'value' => '3',
                'code' => 'variables',
            ],
        ];

        foreach ($data as $d) {
            Setting::create($d);
        }
    }
}
