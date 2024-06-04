<?php

namespace Modules\Company\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Company\Models\Division;
use Modules\Company\Models\Position;
use Illuminate\Support\Facades\Schema;
use \PhpOffice\PhpSpreadsheet\Reader\Xlsx as Reader;

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

        $reader = new Reader();
        
        $service = new \Modules\Hrd\Services\EmployeeService();
        $response = $service->readFile(public_path('static_file/employee.xlsx'));
        
        $positions = collect(array_values($response))->pluck('position_id')->unique()->filter(function ($item) {
            return $item != null;
        })->values()->toArray();

        $out = [];
        foreach ($positions as $key => $position) {
            $position = ltrim(rtrim($position));

            $out[$key]['name'] = $position;

            if ($position == 'Admin Staff') {
                $out[$key]['division_id'] = $finance->id;
            } else if (
                $position == 'HR Officer' || 
                $position == 'HR & TA Admin'
            ) {
                $out[$key]['division_id'] = $hr->id;
            } else if (
                $position == 'IT Technical Support' ||
                $position == 'Full Stack Developer'
            ) {
                $out[$key]['division_id'] = $it->id;
            } else if (
                $position == 'Lead Marcomm' ||
                $position == 'Marketing Staff'
            ) {
                $out[$key]['division_id'] = $marketing->id;
            } else {
                $out[$key]['division_id'] = $production->id;
            }
        }

        foreach ($out as $o) {
            Position::create($o);
        }

        // $positions = [
        //     [
        //         'name' => 'HR Generalist',
        //         'division_id' => $hr->id,
        //     ],
        //     [
        //         'name' => 'Admin & Finance',
        //         'division_id' => $finance->id,
        //     ],
        //     [
        //         'name' => 'IT Technical Support',
        //         'division_id' => $it->id,
        //     ],
        //     [
        //         'name' => 'Fullstack Developer',
        //         'division_id' => $it->id,
        //     ],
        //     [
        //         'name' => 'Marketing',
        //         'division_id' => $marketing->id,
        //     ],
        //     [
        //         'name' => 'Project Manager',
        //         'division_id' => $production->id,
        //     ],
        //     [
        //         'name' => 'Lead Project Manager',
        //         'division_id' => $production->id,
        //     ],
        //     [
        //         'name' => '3D Modeller',
        //         'division_id' => $production->id,
        //     ],
        //     [
        //         'name' => 'Compositor',
        //         'division_id' => $production->id,
        //     ],
        //     [
        //         'name' => 'Generalist',
        //         'division_id' => $production->id,
        //     ],
        //     [
        //         'name' => 'Animator',
        //         'division_id' => $production->id,
        //     ],
        //     [
        //         'name' => 'Lead Entertaintment',
        //         'division_id' => $entertainment->id,
        //     ],
        //     [
        //         'name' => 'VJ',
        //         'division_id' => $entertainment->id,
        //     ],
        // ];

        // foreach ($positions as $position) {
        //     Position::create($position);
        // }

        Schema::enableForeignKeyConstraints();
    }
}
