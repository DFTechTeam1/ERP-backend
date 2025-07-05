<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use KodePandai\Indonesia\IndonesiaDatabaseSeeder;
use Modules\Company\Database\Seeders\DivisionSeeder;
use Modules\Company\Database\Seeders\PositionSeeder;
use Modules\Hrd\Database\Seeders\EmployeeSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            IndonesiaDatabaseSeeder::class,
            // WorldRegionSeeder::class,
            RolePermissionSeeder::class,
            DivisionSeeder::class,
            PositionSeeder::class,
            UserSeeder::class,
            MenuSeeder::class,
            KanbanSettingSeeder::class,
            GeneralSettingSeeder::class,
            EmailSettingSeeder::class,
            VariableSettingSeeder::class,
            EmployeeSeeder::class,
            ProjectClassSeeder::class,
            AppendPermissionSeeder::class,
        ]);
    }
}
