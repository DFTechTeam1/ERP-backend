<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Company\Models\Position;
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

        $director = Position::where('name', 'Lead Project Manager')
            ->orWhere('name', 'Head of Creative')
            ->get();

        $marketing = Position::where('name', 'Lead Marcomm')
            ->first();

        $pm = Position::where('name', 'Lead Project Manager')
            ->orWhere('name', 'Project Manager')
            ->get();
    
        $modeller = Position::where('name', '3D Modeller')
            ->first();

        $data = [
            [
                'key' => 'position_as_directors',
                'value' => collect($director)->pluck('uid')->toArray(),
                'code' => 'variables',
            ],
            [
                'key' => 'position_as_marketing',
                'value' => $marketing->uid,
                'code' => 'variables',
            ],
            [
                'key' => 'position_as_project_manager',
                'value' => collect($pm)->pluck('uid')->toArray(),
                'code' => 'variables',
            ],
            [
                'key' => 'special_production_position',
                'value' => $modeller->uid,
                'code' => 'variables',
            ],
            [
                'key' => 'days_to_raise_deadline_alert',
                'value' => 3,
                'code' => 'variables',
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
