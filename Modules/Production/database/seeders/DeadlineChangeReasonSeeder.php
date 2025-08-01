<?php

namespace Modules\Production\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Production\Models\DeadlineChangeReason;

class DeadlineChangeReasonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $payload = [
            ['name' => 'Revisi datang dari client'],
            ['name' => 'Prioritas event lain'],
            ['name' => 'Rendering lama'],
            ['name' => 'Render error'],
            ['name' => 'Member izin / sakit / tidak masuk'],
            ['name' => 'Feedback client lama'],
            ['name' => 'Device bermasalah'],
        ];

        foreach ($payload as $data) {
            DeadlineChangeReason::create($data);
        }
    }
}
