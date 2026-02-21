<?php

namespace Modules\Hrd\Database\Seeders;

use Illuminate\Database\Seeder;

class HrdDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $this->call([]);
        $this->call([
            EmployeeSeeder::class,
            DefaultAllowanceSeedSeeder::class
        ]);
    }
}
