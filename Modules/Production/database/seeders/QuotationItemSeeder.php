<?php

namespace Modules\Production\Database\Seeders;

use Illuminate\Database\Seeder;

class QuotationItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $payload = [
            ['name' => 'LED DIgital Content'],
            ['name' => 'Opening Sequence Content'],
            ['name' => 'Entertainment LED Concept'],
            ['name' => 'Event Stationary'],
        ];

        foreach ($payload as $data) {
            \Modules\Production\Models\QuotationItem::create($data);
        }
    }
}
