<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProjectClassSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \Modules\Company\Models\ProjectClass::truncate();

        $payload = [
            [
                'name' => 'B (Standard)',
                'maximal_point' => 10,
                'color' => '#43A047',
            ],
            [
                'name' => 'S (Spesial)',
                'maximal_point' => 18,
                'color' => '#FFB74D',
            ],
            [
                'name' => 'A (besar)',
                'maximal_point' => 15,
                'color' => '#F4511E',
            ],
            [
                'name' => 'C (Budget)',
                'maximal_point' => 8,
                'color' => '#AED581',
            ],
            [
                'name' => 'D (Template)',
                'maximal_point' => 5,
                'color' => '#039BE5',
            ],
        ];

        foreach ($payload as $data) {
            \Modules\Company\Models\ProjectClass::create($data);
        }
    }
}
