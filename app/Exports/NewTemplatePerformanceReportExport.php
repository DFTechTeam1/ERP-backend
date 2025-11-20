<?php

namespace App\Exports;

use App\Services\ExportImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Modules\Hrd\Repository\EmployeePointProjectRepository;
use Modules\Hrd\Repository\EmployeeRepository;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class NewTemplatePerformanceReportExport implements FromView, ShouldAutoSize, WithEvents, ShouldQueue
{
    use Exportable;

    protected $startDate;

    protected $endDate;

    protected $userId;

    protected $filepath;

    public function __construct(string $startDate = '', string $endDate = '', int $userId, string $filepath)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->userId = $userId;
        $this->filepath = $filepath;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function view(): View
    {
        ini_set('memory_limit', '512M');
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

            $output[$project->project->name][] = [
                'tasks' => implode(',', $tasks),
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
            ];
        }
        logging('output', [count($output)]);

        return view('hrd::new-export-performance-report', ['points' => $output]);
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
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                // set header to bold
                $event->sheet->getDelegate()->getStyle('A1:I2')->applyFromArray([
                    'font' => [
                        'bold' => true,
                    ],
                ]);

                // notify user
                (new \App\Services\ExportImportService)->handleSuccessProcessing(payload: [
                    'description' => 'Your performance report file is ready. Please check your inbox to download the file.',
                    'message' => '<p>Click <a href="'.$this->filepath.'" target="__blank">here</a> to download your performance report</p>',
                    'area' => 'finance',
                    'user_id' => $this->userId,
                ]);
            },
        ];
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
