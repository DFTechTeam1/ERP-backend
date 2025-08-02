<?php

namespace Modules\Addon\Database\Seeders;

use Illuminate\Database\Seeder;

class AddonDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            AddonSeeder::class,
        ]);
    }
}
