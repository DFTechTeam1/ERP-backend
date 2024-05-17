<?php

namespace Modules\Inventory\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Inventory\Models\InventoryType;

class InventoryTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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
            $d['slug'] = strtolower(implode('_', explode(' ', $d['name'])));
            InventoryType::create($d);
        }
    }
}
