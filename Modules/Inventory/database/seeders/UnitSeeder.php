<?php

namespace Modules\Inventory\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $this->call([]);
        Schema::disableForeignKeyConstraints();

        \Modules\Inventory\Models\Unit::create([
            'name' => 'Unit',
        ]);

        Schema::enableForeignKeyConstraints();
    }
}
