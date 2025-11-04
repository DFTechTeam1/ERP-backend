<?php

namespace Modules\Production\Database\Seeders;

use Illuminate\Database\Seeder;

class ProductionDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            ProjectSeeder::class,
            PriceSettingSeeder::class,
            QuotationSettingSeeder::class,
            QuotationItemSeeder::class,
            DeadlineChangeReasonSeeder::class,
        ]);
    }
}
