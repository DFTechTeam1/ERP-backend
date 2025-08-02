<?php

namespace Modules\Inventory\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $this->call([]);
        Schema::disableForeignKeyConstraints();

        \Modules\Inventory\Models\Supplier::truncate();

        $suppliers = [
            ['name' => 'Tokopedia'],
            ['name' => 'Shopee'],
        ];

        foreach ($suppliers as $data) {
            \Modules\Inventory\Models\Supplier::create($data);
        }

        Schema::enableForeignKeyConstraints();
    }
}
