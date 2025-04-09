<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Modules\Hrd\Repository\EmployeePointProjectRepository;
use Modules\Hrd\Repository\EmployeeRepository;

class NewTemplatePerformanceReportExport implements FromView, ShouldAutoSize
{
    use Exportable;

    protected $startDate;
    protected $endDate;

    public function __construct(string $startDate = '', string $endDate = '')
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function view(): View
    {
        $employeeRepo = new EmployeeRepository();
        $pointProjectRepo = new EmployeePointProjectRepository();
        $projects = $pointProjectRepo->list(
            select: "id,employee_point_id,project_id,total_point,additional_point",
            relation: [
                'project:id,name,project_date',
                'project.personInCharges:id,project_id,pic_id',
                'project.personInCharges.employee:id,name',
                'details:id,point_id,task_id',
                'employeePoint:id,type,employee_id',
                'employeePoint.employee:id,name,position_id',
                'employeePoint.employee.position:id,name',
                'details.productionTask:id,name',
                'details.entertainmentTask:id,project_song_list_id',
                'details.entertainmentTask.song:id,name'
            ],
            whereHas: [
                ['relation' => 'project', 'query' => "project_date BETWEEN '{$this->startDate}' AND '{$this->endDate}'"]
            ]
        );

        $output = [];
        foreach ($projects as $project) {
            $type = $project->employeePoint->type;

            $tasks = [];
            if ($type == 'production') {
                $tasks = collect($project->details)->pluck('productionTask.name')->toArray();
            } else if ($type == 'entertainment') {
                $tasks = collect($project->details)->pluck('entertainmentTask.song.name')->toArray();
            }

            $pics = [];
            if ($project->project->personInCharges->count() > 0) {
                $pics = collect($project->project->personInCharges)->pluck('employee.name')->toArray();
            }

            $output[$project->project->name][] = [
                'tasks' => implode(",", $tasks),
                'point' => $project->total_point - $project->additional_point,
                'additional_point' => $project->additional_point,
                'total_point' => $project->total_point,
                'project_name' => $project->project->name,
                'employee_name' => $project->employeePoint->employee->name,
                'pics' => implode(',', $pics),
                'position' => $project->employeePoint->employee->position ? $project->employeePoint->employee->position->name : '-',
            ];
        }

        return view('hrd::new-export-performance-report', ['points' => $output]);
    }
}
