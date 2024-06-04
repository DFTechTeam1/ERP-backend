<?php

namespace App\Imports;

use App\Imports\Employee\FulltimeCompile;
use App\Imports\Employee\FulltimeResponse;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class EmployeeImport implements WithMultipleSheets
{

    public function sheets(): array
    {
        return [
            'Fulltime Compile' => new FulltimeCompile(),
            'Fulltime Response' => new FulltimeResponse(),
        ];
    }
}
