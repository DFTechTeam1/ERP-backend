<?php

namespace Modules\Hrd\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Company\Models\Division;
use Modules\Company\Models\DivisionBackup;
use Modules\Hrd\Models\Allowance;
use Modules\Hrd\Models\DivisionAllowance;

class DefaultAllowanceSeedSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $mealAllowance = Allowance::create([
            'name' => 'Meal Allowance',
            'period' => 'per_day',
            'status' => 'active'
        ]);

        $marketingDivision = DivisionBackup::where('name', 'Marcomm')->first();
        $productionDivision = DivisionBackup::where('name', 'Production')->first();
        $entertainmentDivision = DivisionBackup::where('name', 'Entertainment')->first();

        $divisionAllowances = [
            [
                'division_id' => $marketingDivision->id,
                'allowance_id' => $mealAllowance->id,
                'amount' => 50000,
                'is_default' => true,
                'allowance_type' => 'event_day',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'division_id' => $productionDivision->id,
                'allowance_id' => $mealAllowance->id,
                'amount' => 60000,
                'is_default' => true,
                'allowance_type' => 'event_day',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'division_id' => $entertainmentDivision->id,
                'allowance_id' => $mealAllowance->id,
                'amount' => 80000,
                'is_default' => true,
                'allowance_type' => 'event_day',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        DivisionAllowance::insert($divisionAllowances);
    }
}
