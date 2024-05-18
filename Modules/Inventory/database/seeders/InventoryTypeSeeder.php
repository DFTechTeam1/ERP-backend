<?php

namespace Modules\Inventory\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Inventory\Models\InventoryType;
use Illuminate\Support\Facades\Schema;

class InventoryTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        InventoryType::truncate();

        $data = [
            ['name' => 'PC Rakit'],
            ['name' => 'Monitor'],
            ['name' => 'Mouse'],
            ['name' => 'Keyboard'],
            ['name' => 'PSU'],
            ['name' => 'HDD & SSD'],
            ['name' => 'RAM'],
            ['name' => 'VGA'],
            ['name' => 'MOBO'],
            ['name' => 'Processor'],
            ['name' => 'CPU Cooler'],
            ['name' => 'Laptop'],
            ['name' => 'PC Rakitan'],
            ['name' => 'Pheriperal'],
            ['name' => 'Proyektor'],
            ['name' => 'Nas Server'],
            ['name' => 'Networking'],
            ['name' => 'Printer'],
            ['name' => 'Kamera'],
        ];

        foreach ($data as $d) {
            $slug = strtolower(implode('_', explode(' ', $d['name'])));

            \Modules\Inventory\Models\InventoryType::create([
                'name' => $d['name'],
                'slug' => $slug,
            ]);
        }

        Schema::enableForeignKeyConstraints();
    }
}
