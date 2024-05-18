<?php

namespace Modules\Company\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Company\Models\Division;
use Modules\Company\Models\Position;
use Illuminate\Support\Facades\Schema;

class PositionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        Position::truncate();

        $hr = Division::findByName('hr');
        $finance = Division::findByName('finance');
        $it = Division::findByName('it');
        $marketing = Division::findByName('marketing');
        $production = Division::findByName('Production');
        $entertainment = Division::findByName('Entertainment');

        $positions = [
            [
                'name' => 'HR Generalist',
                'division_id' => $hr->id,
            ],
            [
                'name' => 'Admin & Finance',
                'division_id' => $finance->id,
            ],
            [
                'name' => 'IT Technical Support',
                'division_id' => $it->id,
            ],
            [
                'name' => 'Fullstack Developer',
                'division_id' => $it->id,
            ],
            [
                'name' => 'Marketing',
                'division_id' => $marketing->id,
            ],
            [
                'name' => 'Project Manager',
                'division_id' => $production->id,
            ],
            [
                'name' => 'Lead Project Manager',
                'division_id' => $production->id,
            ],
            [
                'name' => '3D Modeller',
                'division_id' => $production->id,
            ],
            [
                'name' => 'Compositor',
                'division_id' => $production->id,
            ],
            [
                'name' => 'Generalist',
                'division_id' => $production->id,
            ],
            [
                'name' => 'Animator',
                'division_id' => $production->id,
            ],
            [
                'name' => 'Lead Entertaintment',
                'division_id' => $entertainment->id,
            ],
            [
                'name' => 'VJ',
                'division_id' => $entertainment->id,
            ],
        ];

        foreach ($positions as $position) {
            Position::create($position);
        }

        Schema::enableForeignKeyConstraints();
    }
}
