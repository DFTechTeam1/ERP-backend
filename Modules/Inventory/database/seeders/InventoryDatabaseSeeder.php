<?php

namespace Modules\Inventory\Database\Seeders;

use Illuminate\Database\Seeder;

class InventoryDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            SupplierSeeder::class,
            BrandSeeder::class,
            UnitSeeder::class,
            InventoryTypeSeeder::class,
            InventorySeeder::class,
        ]);
    }
}
