<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SummaryFinanceExport implements FromView, ShouldAutoSize, WithStyles
{
    public function view(): View
    {
        return view('finance.report.summaryExport');
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();

        // set border
        return [
            "A1:K{$lastRow}" => [
                'allBorders' => [
                    'borderStyle' => ''
                ]
            ]
        ];
    }

}
