<?php

namespace Modules\Company\Database\Seeders;

use Illuminate\Database\Seeder;

class CompanyDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $this->call([]);

        $this->call([
            DivisionSeeder::class,
            PositionSeeder::class,
            BankSeeder::class,
            JobLevelSeeder::class,
            ModellerSeeder::class,
            CompanySettingSeeder::class,
        ]);
    }
}
