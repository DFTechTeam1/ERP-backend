<?php

namespace App\Imports\Employee;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class FulltimeCompile extends DefaultValueBinder implements SkipsEmptyRows, ToCollection, WithCalculatedFormulas, WithCustomValueBinder
{
    public function collection(Collection $collection)
    {
        //
    }

    public function bindValue(Cell $cell, $value)
    {
        if (is_numeric($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_NUMERIC);

            return 'oke';
        }

        // else return default behavior
        return parent::bindValue($cell, $value);
    }
}
