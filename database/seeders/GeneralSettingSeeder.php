<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Company\Models\Setting;
use Spatie\Permission\Models\Role;

class GeneralSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Setting::where('code', 'general')
            ->delete();

        $productionRole = Role::findByName('production');
        $pmRole = Role::findByName('project manager');
        $suRole = Role::findByName('root');

        $data = [
            [
                'key' => 'app_name',
                'value' => 'DF Data Center',
                'code' => 'general',
            ],
            [
                'key' => 'super_user_role',
                'value' => $suRole->id,
                'code' => 'general',
            ],
            [
                'key' => 'production_staff_role',
                'value' => [$productionRole->id],
                'code' => 'general',
            ],
            [
                'key' => 'project_manager_role',
                'value' => $pmRole->id,
                'code' => 'general',
            ],
            [
                'key' => 'board_as_3d',
                'value' => '1',
                'code' => 'general',
            ],
            [
                'key' => 'board_as_compositing',
                'value' => '2',
                'code' => 'general',
            ],
            [
                'key' => 'board_as_animating',
                'value' => '3',
                'code' => 'general',
            ],
            [
                'key' => 'board_as_finalize',
                'value' => '4',
                'code' => 'general',
            ],
        ];

        foreach ($data as $d) {
            if (gettype($d['value']) == 'array') {
                $d['value'] = json_encode($d['value']);
            }
            
            Setting::create($d);
        }
    }
}
