<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProjectClassSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $payload = [
            [
                'name' => 'B (Standard)',
                'maximal_point' => 10,
            ],
            [
                'name' => 'S (Special)',
                'maximal_point' => 18,
            ],
            [
                'name' => 'A (Big)',
                'maximal_point' => 15,
            ],
            [
                'name' => 'C (Budget)',
                'maximal_point' => 8,
            ],
            [
                'name' => 'D (Template)',
                'maximal_point' => 5,
            ],
        ];

        foreach ($payload as $data) {
            \Modules\Company\Models\ProjectClass::create($data);
        }
    }
}
