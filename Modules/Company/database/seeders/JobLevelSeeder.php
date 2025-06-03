<?php

namespace Modules\Company\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Company\Models\JobLevel;

class JobLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        JobLevel::truncate();

        $payload = [
            'C-Level',
            'Supervisor',
            'Staff',
            'Lead',
            'Junior Staff',
        ];

        foreach ($payload as $name) {
            JobLevel::create(['name' => $name]);
        }
    }
}
