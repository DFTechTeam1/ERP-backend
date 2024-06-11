<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Company\Models\Setting;

class GeneralSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Setting::where('code', 'general')
            ->delete();

        $data = [
            [
                'key' => 'app_name',
                'value' => 'DF Data Center',
                'code' => 'general',
            ],
            [
                'key' => 'board_start_calcualted',
                'value' => '3',
                'code' => 'general',
            ],
            [
                'key' => 'super_user_role',
                'value' => '1',
                'code' => 'general',
            ],
        ];

        foreach ($data as $d) {
            Setting::create($d);
        }
    }
}
