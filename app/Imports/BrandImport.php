<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;

class BrandImport implements SkipsEmptyRows, ToCollection
{
    public function collection(Collection $collection) {}
}
