<?php

namespace App\Exports;

use App\Services\ExportImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Modules\Hrd\Repository\EmployeePointProjectRepository;
use Modules\Hrd\Repository\EmployeeRepository;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class NewTemplatePerformanceReportExport implements FromView, ShouldQueue, WithEvents
{
    use Exportable;

    protected $startDate;

    protected $endDate;

    protected $userId;

    protected $filepath;

    public function __construct(string $startDate, string $endDate, int $userId, string $filepath)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->userId = $userId;
        $this->filepath = $filepath;
    }

    /**
     * @return Collection
     */
    public function view(): View
    {
        ini_set('memory_limit', '1024M');
        logging('PHP Memory Limit: ', [ini_get('memory_limit')]);
        logging('PHP Memory Usage: ', [memory_get_usage(true)]);
        logging('Max Execution Time: ', [ini_get('max_execution_time')]);
        $employeeRepo = new EmployeeRepository;
        $pointProjectRepo = new EmployeePointProjectRepository;
        $projects = $pointProjectRepo->list(
            select: 'id,employee_point_id,project_id,total_point,additional_point,calculated_prorate_point,prorate_point,original_point',
            relation: [
                'project' => function ($queryProject) {
                    $queryProject->selectRaw('id,name,project_date')
                        ->with([
                            'personInCharges:id,project_id,pic_id',
                            'personInCharges.employee:id,name',
                            'entertainmentTaskSong.song:id,name',
                            'entertainmentTaskSong.employee:id,name,position_id',
                            'entertainmentTaskSong.employee.position:id,name',
                            'feedbacks:id,project_id,pic_id,feedback',
                            'feedbacks.pic:id,nickname',
                        ]);
                },
                'details:id,point_id,task_id',
                'employeePoint:id,type,employee_id',
                'employeePoint.employee:id,name,position_id',
                'employeePoint.employee.position:id,name',
                'details.productionTask:id,name',
                'details.entertainmentTask:id,project_song_list_id',
                'details.entertainmentTask.song:id,name',
            ],
            whereHas: [
                ['relation' => 'project', 'query' => "project_date BETWEEN '{$this->startDate}' AND '{$this->endDate}'"],
            ]
        );

        $output = [];
        $entertainmentList = [];
        foreach ($projects as $project) {
            $type = $project->employeePoint->type;

            $tasks = [];
            if ($type == 'production') {
                $tasks = collect($project->details)->pluck('productionTask.name')->toArray();
            } elseif ($type == 'entertainment') {
                $tasks = collect($project->details)->pluck('entertainmentTask.song.name')->toArray();
            }

            $pics = [];
            if ($project->project->personInCharges->count() > 0) {
                $pics = collect($project->project->personInCharges)->pluck('employee.name')->toArray();
            }

            if ($project->project->entertainmentTaskSong->count() > 0) {
                $entertainmentList[$project->project->name] = $project->project->entertainmentTaskSong->groupBy('employee_id')
                    ->map(function ($item) use ($pics) {
                        return [
                            'tasks' => $item->pluck('song.name')->implode(','),
                            'total_tasks' => $item->count(),
                            'point' => 1,
                            'additional_point' => 0,
                            'calculated_prorate_point' => 0,
                            'prorate_point' => 0,
                            'original_point' => 0,
                            'total_point' => 0,
                            'project_name' => $item->first()->project->name,
                            'employee_name' => $item->first()->employee->name,
                            'pics' => implode(',', $pics),
                            'position' => $item->first()->employee->position ? $item->first()->employee->position->name : '-',
                            'feedbacks' => $item->first()->project->feedbacks->map(function ($feedback) {
                                return $feedback->pic->nickname.': '.$feedback->feedback;
                            }),
                        ];
                    })->toArray();
            }

            $output[$project->project->name][] = [
                'tasks' => implode(',', $tasks),
                'total_tasks' => count($tasks),
                'point' => $project->total_point - $project->additional_point,
                'additional_point' => $project->additional_point,
                'calculated_prorate_point' => $project->calculated_prorate_point,
                'prorate_point' => $project->prorate_point,
                'original_point' => $project->original_point,
                'total_point' => $project->total_point,
                'project_name' => $project->project->name,
                'employee_name' => $project->employeePoint->employee->name,
                'pics' => implode(',', $pics),
                'position' => $project->employeePoint->employee->position ? $project->employeePoint->employee->position->name : '-',
                'feedbacks' => $project->project->feedbacks->map(function ($feedback) {
                    return $feedback->pic->nickname.': '.$feedback->feedback;
                }),
            ];
        }

        // merge $output and $entertainmentList based on project_name
        foreach ($entertainmentList as $projectName => $entertainmentItems) {
            if (isset($output[$projectName])) {
                $output[$projectName] = array_merge($output[$projectName], $entertainmentItems);
            } else {
                $output[$projectName] = $entertainmentItems;
            }
        }

        return view('hrd::new-export-performance-report', [
            'points' => $output,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $this->applySheetStyling($event->sheet->getDelegate());

                // notify user
                (new ExportImportService)->handleSuccessProcessing(payload: [
                    'description' => 'Your performance report file is ready. Please check your inbox to download the file.',
                    'message' => '<p>Click <a href="'.$this->filepath.'" target="__blank">here</a> to download your performance report</p>',
                    'area' => 'finance',
                    'user_id' => $this->userId,
                ]);
            },
        ];
    }

    /**
     * Style the rendered sheet for readability: a title banner (row 1), a coloured header
     * (row 2), bordered and zebra-striped data (row 3+), wrapped long-text columns, centered
     * numeric point columns, fixed column widths, a frozen header and an auto-filter.
     */
    protected function applySheetStyling(Worksheet $sheet): void
    {
        $lastColumn = 'K';
        $headerRow = 2;
        $firstDataRow = 3;
        $lastRow = $sheet->getHighestRow();

        // ---- Title banner (row 1) ----
        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F3864']],
        ]);

        // ---- Header row (row 2) ----
        $sheet->getRowDimension($headerRow)->setRowHeight(26);
        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2E5496']],
        ]);

        // ---- Borders across the whole table ----
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B7C0CE']],
            ],
        ]);

        // ---- Data area (row 3+) ----
        if ($lastRow >= $firstDataRow) {
            $sheet->getStyle("A{$firstDataRow}:{$lastColumn}{$lastRow}")
                ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

            // No + the point/count columns read best centered
            $sheet->getStyle("A{$firstDataRow}:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("G{$firstDataRow}:J{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("G{$firstDataRow}:J{$lastRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);

            // long-text columns wrap instead of overflowing
            $sheet->getStyle("F{$firstDataRow}:F{$lastRow}")->getAlignment()->setWrapText(true);
            $sheet->getStyle("K{$firstDataRow}:K{$lastRow}")->getAlignment()->setWrapText(true);

            // zebra striping
            for ($row = $firstDataRow; $row <= $lastRow; $row++) {
                if (($row - $firstDataRow) % 2 === 1) {
                    $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F5FA']],
                    ]);
                }
            }
        }

        // ---- Column widths (autosize is off; text columns need room to wrap) ----
        $widths = [
            'A' => 6, 'B' => 28, 'C' => 20, 'D' => 24, 'E' => 18, 'F' => 46,
            'G' => 12, 'H' => 11, 'I' => 13, 'J' => 11, 'K' => 34,
        ];
        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setAutoSize(false)->setWidth($width);
        }

        // ---- Freeze the title + header, filter on the header ----
        $sheet->freezePane("A{$firstDataRow}");
        $sheet->setAutoFilter("A{$headerRow}:{$lastColumn}{$headerRow}");
    }

    public function failed(\Throwable $exception)
    {
        (new ExportImportService)->handleErrorProcessing(payload: [
            'description' => 'Failed to export finance report',
            'message' => $exception->getMessage(),
            'area' => 'finance',
            'user_id' => $this->userId,
        ]);
    }
}
