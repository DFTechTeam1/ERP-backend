<?php

namespace Modules\Company\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Modules\Company\Models\Division;
use Modules\Company\Models\Position;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as Reader;

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

        $reader = new Reader;

        $service = new \Modules\Hrd\Services\EmployeeService;
        $response = $service->readFile(public_path('static_file/employee.xlsx'));

        $positions = collect(array_values($response))->pluck('position_raw')->unique()->filter(function ($item) {
            return $item != null;
        })->values()->toArray();

        $out = [];
        foreach ($positions as $key => $position) {
            $position = ltrim(rtrim($position));

            $out[$key]['name'] = $position;

            if ($position == 'Admin Staff') {
                $out[$key]['division_id'] = $finance->id;
            } elseif (
                $position == 'HR Officer' ||
                $position == 'HR & TA Admin'
            ) {
                $out[$key]['division_id'] = $hr->id;
            } elseif (
                $position == 'IT Technical Support' ||
                $position == 'Full Stack Developer'
            ) {
                $out[$key]['division_id'] = $it->id;
            } elseif (
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

        $this->command->info('success seed');

        Schema::enableForeignKeyConstraints();
    }
}
