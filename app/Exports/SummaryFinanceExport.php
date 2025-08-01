<?php

namespace App\Exports;

use App\Enums\Production\ProjectDealStatus;
use App\Services\ExportImportService;
use App\Services\GeneralService;
use Illuminate\Contracts\Queue\ShouldQueue;
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

class SummaryFinanceExport implements FromView, ShouldAutoSize, WithEvents, ShouldQueue
{
    use Exportable;

    private $payload;

    private $data;

    private $finalLine;

    private $userId;

    private $filepath;

    public function __construct(array $payload, int $userId, $filepath = '')
    {
        $this->payload = $payload;

        $this->userId = $userId;

        $this->filepath = $filepath;
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

                // notify user
                (new \App\Services\ExportImportService)->handleSuccessProcessing(payload: [
                    'description' => 'Your finance summary file is ready. Please check your inbox to download the file.',
                    'message' => '<p>Click <a href="'. $this->filepath .'" target="__blank">here</a> to download your finance report</p>',
                    'area' => 'finance',
                    'user_id' => $this->userId
                ]);
            }
        ];
    }

    public function failed(\Throwable $exception)
    {
        (new ExportImportService)->handleErrorProcessing(payload: [
            'description' => 'Failed to export finance report',
            'message' => $exception->getMessage(),
            'area' => 'finance',
            'user_id' => $this->userId
        ]);
    }
}
