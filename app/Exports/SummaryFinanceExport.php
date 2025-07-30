<?php

namespace App\Exports;

use App\Enums\Production\ProjectDealStatus;
use App\Services\GeneralService;
use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\Exportable;

class SummaryFinanceExport implements FromView, ShouldAutoSize, WithEvents
{
    private $payload;

    private $data;

    private $finalLine;

    public function __construct(array $payload = [])
    {
        $this->payload = $payload;
    }

    public function view(): View
    {
        $data = (new GeneralService)->getFinanceExportData(payload: $this->payload);

        // define number of row of final project
        $finalLine = [];
        foreach ($data as $key => $project) {
            if ($project->status == ProjectDealStatus::Final) {
                $numberOfTransaction = $project->transactions->count();
                $finalLine[] = [
                    'start' => $key + 3,
                    'end' => $key + 3 + $numberOfTransaction - 1
                ];
            }
        }

        $this->finalLine = $finalLine;

        $this->data = $data;

        return view('finance.report.summaryExport', [
            'projects' => $data
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // set filter
                $event->sheet->getDelegate()->setAutoFilter('A1:I1');

                // set borders
                $lastRow = $event->sheet->getDelegate()->getHighestRow();
                $event->sheet->getDelegate()->getStyle("A1:I{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000']
                        ]
                    ]
                ]);

                // set header to bold
                $event->sheet->getDelegate()->getStyle('A1:I2')->applyFromArray([
                    'font' => [
                        'bold' => true
                    ]
                ]);
            }
        ];
    }
}
