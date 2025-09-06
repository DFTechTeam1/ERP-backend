<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

class InventoryGeneralReport implements FromView, WithTitle, ShouldAutoSize, WithEvents
{
    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function view(): View
    {
        return view('reports.inventory.inventory_general', [
            'data' => $this->data
        ]);
    }

    public function title(): string
    {
        return 'General Report';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // set filter
                $event->sheet->getDelegate()->setAutoFilter('B1:G1');

                // set borders
                $lastRow = $event->sheet->getDelegate()->getHighestRow();
                $event->sheet->getDelegate()->getStyle("A1:G{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000']
                        ]
                    ]  
                ]);

                // set header to bold
                $event->sheet->getDelegate()->getStyle('B1:G1')->applyFromArray([
                    'font' => [
                        'bold' => true
                    ]
                ]);

                // notify user
                // (new \App\Services\ExportImportService)->handleSuccessProcessing(payload: [
                //     'description' => 'Your finance summary file is ready. Please check your inbox to download the file.',
                //     'message' => '<p>Click <a href="'. $this->filepath .'" target="__blank">here</a> to download your finance report</p>',
                //     'area' => 'finance',
                //     'user_id' => $this->userId
                // ]);
            }
        ];
    }
}
