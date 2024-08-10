<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;

class BrandImport implements ToCollection, SkipsEmptyRows
{
    /**
    * @param Collection $collection
    */
    public function collection(Collection $collection)
    {
    }
}
