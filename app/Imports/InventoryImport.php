<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;

class InventoryImport implements SkipsEmptyRows, ToCollection
{
    public function collection(Collection $collection)
    {
        //
    }
}
