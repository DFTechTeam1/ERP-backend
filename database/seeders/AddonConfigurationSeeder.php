<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Company\Models\Setting;

class AddonConfigurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Setting::where('code', 'addon')->delete();

        $data = [
            ['code' => 'addon', 'key' => 'server', 'value' => '192.168.100.104'],
            ['code' => 'addon', 'key' => 'folder', 'value' => '/AddOn'],
        ];

        foreach ($data as $setting) {
            Setting::create($setting);
        }
    }
}
