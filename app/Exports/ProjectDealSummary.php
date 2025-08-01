<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ProjectDealSummary implements WithMultipleSheets
{
    public function sheets(): array
    {
        $years = DB::table('project_deals')
            ->selectRaw("DISTINCT(YEAR(project_date)) as year")
            ->get()->toArray();

        if (count($years) == 0) {
            $years = [['year' => 2025]];
        }

        $sheets = [];
        foreach($years as $year)  {
            $sheets[] = new ProjectDealSummaryPerYear((int) $year->year);
        }

        return $sheets;
    }
}
