<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class KanbanSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \Illuminate\Support\Facades\Cache::forget('setting');

        \Modules\Company\Models\Setting::where('code', 'kanban')->delete();

        \Modules\Company\Models\Setting::create([
            'code' => 'kanban',
            'key' => 'default_boards',
            'value' => json_encode([
                ['name' => 'Asset 3D', 'sort' => 0, 'id' => 1],
                ['name' => 'Compositing', 'sort' => 1, 'id' => 2],
                ['name' => 'Animating', 'sort' => 2, 'id' => 3],
                ['name' => 'Finalize', 'sort' => 3, 'id' => 4],
            ]),
        ]);
    }
}
