<?php

namespace App\Imports\Employee;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\HasReferencesToOtherSheets;
use Maatwebsite\Excel\Concerns\ToCollection;

class FulltimeResponse implements HasReferencesToOtherSheets, ToCollection
{
    public function collection(Collection $collection)
    {
        //
    }
}
