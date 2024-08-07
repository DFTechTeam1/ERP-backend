<?php

namespace Modules\Hrd\Services;

use DateTime;

class PerformanceReportService {
    private $repo;

    private $taskPicHistoryRepo;

    private $projectRepo;

    private $employeePointRepo;

    private $taskPicLogRepo;

    private $startDate;

    private $endDate;

    public function __construct()
    {
        $this->taskPicLogRepo = new \Modules\Production\Repository\ProjectTaskPicLogRepository();

        $this->repo = new \Modules\Hrd\Repository\EmployeeRepository();

        $this->taskPicHistoryRepo = new \Modules\Production\Repository\ProjectTaskPicHistoryRepository();

        $this->projectRepo = new \Modules\Production\Repository\ProjectRepository();

        $this->employeePointRepo = new \Modules\Hrd\Repository\EmployeeTaskPointRepository();
    }

    protected function getTotalProjectEmployee(int $employeeId)
    {
        $data = $this->taskPicHistoryRepo->list('DISTINCT(project_id)', 'employee_id = ' . $employeeId, [
            'project' => function ($query) {
                $query->select('id')
                    ->whereBetween('project_date', [$this->startDate, $this->endDate]);
            }
        ]);

        return collect($data)->filter(function ($item) {
            return $item->project;
        })
        ->values()
        ->count();
    }

    protected function getEmployeePoint(int $employeeId)
    {
        $data = $this->employeePointRepo->list('point,additional_point,total_point,project_id', 'employee_id = ' . $employeeId, [
            'project' => function ($q) {
                $q->selectRaw('id,name')
                    ->whereBetween('project_date', [$this->startDate, $this->endDate]);
            }
        ]);

        $data = collect($data)->filter(function ($filter) {
            return $filter->project;
        })->all();

        $output = [];
        foreach ($data as $point) {
            $task = $this->taskPicHistoryRepo->list(
                'id,project_task_id', 
                'project_id = ' . $point->project_id . ' and employee_id = ' . $employeeId, 
                [
                    'task:id,name',
                    'project' => function ($query) {
                        $query->select('id')
                            ->whereBetween('project_date', [$this->startDate, $this->endDate]);
                    }
                ]
            );

            $output[] = [
                'project_name' => $point->project->name,
                'point' => $point->point,
                'additional_point' => $point->additional_point,
                'total_point' => $point->total_point,
                'tasks' => $task,
            ];
        }

        return $output;
    }

    public function performanceDetail(string $employeeUid)
    {
        // validate date filter
        $this->startDate = date('Y-m-d', strtotime('-7 days'));
        $this->endDate = date('Y-m-d');
        if (request('start_date') && request('end_date')) {
            $start = new DateTime(request('start_date'));
            $end = new DateTime(request('end_date'));
            $diff = date_diff($end, $start);
            $daysInMonth = \Carbon\Carbon::parse(request('start_date'))->daysInMonth;

            if ($diff->days > $daysInMonth) {
                return errorResponse(__('global.oneMonthMaxDateFilter'));
                return generalResponse(
                    __('global.oneMonthMaxDateFilter'),
                    true,
                    [],
                    500,
                );
            }

            $this->startDate = date('Y-m-d', strtotime(request('start_date')));
            $this->endDate = date('Y-m-d', strtotime(request('end_date')));
        }

        $employee = $this->repo->show(
            $employeeUid, 
            'id,name,nickname,employee_id,email,position_id,boss_id', 
            [
                'position:id,name', 
                'boss:id,nickname'
            ]
        );
        
        $totalProject = $this->getTotalProjectEmployee($employee->id);

        $point = $this->getEmployeePoint($employee->id);

        $picLog = $this->taskPicLogRepo->list('*', 'employee_id = ' . $employee->id);
        $picLog = collect($picLog)->groupBy('project_task_id')->toArray();
        $log = [];
        foreach ($picLog as $projectTaskId => $taskLog) {
            $pop = array_pop($taskLog);

            $log[] = $pop;
        }

        $log = collect($log)->groupBy('work_type')->toArray();

        $completed = isset($log[\App\Enums\Production\WorkType::Finish->value]) ? count($log[\App\Enums\Production\WorkType::Finish->value]) : 0;
        $revise = isset($log[\App\Enums\Production\WorkType::Revise->value]) ? count($log[\App\Enums\Production\WorkType::Revise->value]) : 0;
        $waiting = isset($log[\App\Enums\Production\WorkType::Assigned->value]) ? count($log[\App\Enums\Production\WorkType::Assigned->value]) : 0;
        $progress = isset($log[\App\Enums\Production\WorkType::OnProgress->value]) ? count($log[\App\Enums\Production\WorkType::OnProgress->value]) : 0;

        $series = [$completed, $revise, $waiting, $progress];

        // if all series is 0, then do not show the chart
        $showChart = true;
        $uniqueSeries = array_values(array_unique($series));
        if (count($uniqueSeries) == 1 && $uniqueSeries[0] == 0) {
            $showChart = false;
        }

        $output = [
            'realtime_report' => [
                'name' => $employee->nickname,
                'uid' => $employeeUid,
                'position' => $employee->position->name,
                'boss' => $employee->boss_id ? $employee->boss->nickname : '-',
                'period' => date('d F', strtotime($this->startDate)) . ' - ' . date('d F', strtotime($this->endDate)),
                'total_point' => collect($point)->pluck('total_point')->sum(),
            ],
            'chart' => [
                'labels' => [__('global.completed'), __('global.revise'), __('global.waitingApproval'), __('global.onProgress')],
                'series' => $series,
                'show_chart' => $showChart,
            ],
            'total_project' => $totalProject,
            'point_detail' => $point,
        ];

        return generalResponse(
            'success',
            false,
            $output,
        );
    }
}