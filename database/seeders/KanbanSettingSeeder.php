<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
                ['name' => 'Backlog', 'sort' => 0],
                ['name' => 'To Do', 'sort' => 1],
                ['name' => 'On Progress', 'sort' => 2],
                ['name' => 'Review By PM', 'sort' => 3],
                ['name' => 'Review By Client', 'sort' => 4],
                ['name' => 'Revise', 'sort' => 5],
                ['name' => 'Completed', 'sort' => 6],
            ])
        ]);
    }
}
