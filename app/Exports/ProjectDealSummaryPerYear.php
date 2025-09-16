<?php

namespace App\Exports;

use App\Services\GeneralService;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ProjectDealSummaryPerYear implements FromView, ShouldAutoSize, WithEvents, WithTitle
{
    private $year;

    public function __construct(int $year)
    {
        $this->year = $year;
    }

    public function view(): View
    {
        $service = new GeneralService;

        $output = $service->getProjectDealSummary($this->year);

        $projects = collect([]);
        if (
            (isset($output['data'])) &&
            (isset($output['data']['projects']))
        ) {
            $projects = $output['data']['projects'];
        }

        return view('finance::reports.deals', compact('projects'));
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()->setAutoFilter('A1:Q1');

                // set background
                $event->sheet->getDelegate()->getStyle('A1:Q1')->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color' => ['rgb' => '000000'],
                    ],
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'ffffff'],
                    ],
                ]);

                // set borders
                $lastRow = $event->sheet->getDelegate()->getHighestRow();
                $event->sheet->getDelegate()->getStyle("A1:Q{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);
            },
        ];
    }

    public function title(): string
    {
        return (string) $this->year;
    }
}
