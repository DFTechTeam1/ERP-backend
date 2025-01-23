<?php

namespace Modules\Hrd\Services;

use App\Enums\Employee\Status;
use Carbon\Carbon;
use DateTime;
use Modules\Hrd\Models\Employee;

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

    protected function getEmployeePoint(int $employeeId, string $startDate = '', string $endDate = '')
    {
        if (empty($startDate)) {
            $startDate = $this->startDate;
        }
        if (empty($endDate)) {
            $endDate = $this->endDate;
        }
        $data = $this->employeePointRepo->list('point,additional_point,total_point,project_id,employee_id', 'employee_id = ' . $employeeId, [
            'project' => function ($q) use ($startDate, $endDate) {
                $q->selectRaw('id,name')
                    ->whereBetween('project_date', [$startDate, $endDate]);
            },
            'employee:id,name,employee_id,position_id',
            'employee.position:id,name'
        ]);

        $data = collect((object) $data)->filter(function ($filter) {
            return $filter->project;
        })->all();

        $output = [];
        foreach ($data as $point) {
            $task = $this->taskPicHistoryRepo->list(
                'id,project_task_id', 
                'project_id = ' . $point->project_id . ' and employee_id = ' . $employeeId, 
                [
                    'task:id,name',
                    'taskLog' => function ($logQuery) {
                        $logQuery
                            ->with('task:id,name')
                            ->where('work_type', \App\Enums\Production\WorkType::Assigned->value);
                    }
                ]
            );

            $task = collect((object) $task)->map(function ($mapping) {
                
                return [
                    'name' => $mapping->taskLog[0]->task->name,
                    'assigned_at' => $mapping->taskLog[0]->time_added,
                ];
            })->all();

            $output[] = [
                'project_name' => $point->project->name,
                'point' => $point->point,
                'additional_point' => $point->additional_point,
                'total_point' => $point->total_point,
                'tasks' => $task,
                'employee' => $point->employee
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

    /**
     * Import employee point based on user input
     * User can be choose:
     * 1. All Employee and all data
     * 2. Selected employee
     * 3. Selected date range
     * 4. Maximum is 1 month period
     *
     * $payload will have
     * bool all_employee -> required (false or true)
     * array employee_uids -> nullable, required if all_employee is false
     * string date_start -> nullable
     * end date_start -> nullable
     * 
     * Default date is 1 period
     * 
     * @param array $payload
     * @return void
     */
    public function importEmployeePoint(array $payload)
    {
        try {
            $where = '';
    
            // get the employee ids
            if ($payload['all_employee'] == 1) {
                $employeeUids = $this->getAllEmployeeIdForPoint();
            } else {
                $employeeUids = collect($payload['employee_uids'])->map(function ($item) {
                    return getIdFromUid($item, new Employee());
                })->toArray();
            }
            $employeeUidsCombine = implode(',', $employeeUids);
            $where .= "employee_id IN ({$employeeUidsCombine})";
    
            // get date range
            if (empty($payload['start_date']) && empty($payload['end_date'])) {
                $where = $this->setDefaultPeriodQueryForPoint($where);
            } else if (!empty($payload['start_date']) && !empty($payload['end_date'])) {
                $formatDate = $this->formatPointQueryDate($payload);
                
                $where .= $where .= "DATE(created_at) BETWEEN {$formatDate['start']} AND {$formatDate['end']}";
            } else if (!empty($payload['start_date']) && empty($payload['end_date'])) {
                $payload['end_date'] = date('Y-m-d');
                $formatDate = $this->formatPointQueryDate($payload);
                
                $where .= $where .= "DATE(created_at) BETWEEN {$formatDate['start']} AND {$formatDate['end']}";
            } else if (empty($payload['start_date']) && !empty($payload['end_date'])) {
                $payload['start_end'] = date('Y-m-d');
                $formatDate = $this->formatPointQueryDate($payload);
                
                $where .= $where .= "DATE(created_at) BETWEEN {$formatDate['start']} AND {$formatDate['end']}";
            }

            $totalProject = $this->getTotalProjectEmployee($employeeUids[0]);

            $points = [];
            foreach ($employeeUids as $employeeId) {
                $pointResult = $this->getEmployeePoint($employeeId, $payload['start_date'], $payload['end_date']);

                if (count($pointResult) > 0) {
                    $pointResult = collect($pointResult)->filter(function ($filter) {
                        return $filter['point'] > 0;
                    })->values()->toArray();

                    $points[] = $pointResult;
                }
            }

            $excel = new \App\Services\ExcelService();

            $excel->createSheet('Report', 0);
            $excel->setActiveSheet('Report');

            $excel->setValue('A1', 'REPORT PERFORMANCE REPORT ' . $this->startDate . ' - ' . $this->endDate);
            $excel->mergeCells('A1:H1');
            $excel->setAsBold('A1');
            
            // header
            $excel->setValue('A4', 'Nama');
            $excel->setValue('B4', 'Employee ID');
            $excel->setValue('C4', 'Posisi');
            $excel->setValue('D4', 'Event');
            $excel->setValue('E4', 'Tugas');
            $excel->setValue('F4', 'Poin');
            $excel->setValue('G4', 'Tambahan Poin');
            $excel->setValue('H4', 'Total Poin');

            $excel->setAsBold('A4');
            $excel->setAsBold('B4');
            $excel->setAsBold('C4');
            $excel->setAsBold('D4');
            $excel->setAsBold('E4');
            $excel->setAsBold('F4');
            $excel->setAsBold('G4');
            $excel->setAsBold('H4');

            // fill up the template
            $indexing = 5;
            $indexTask = 5;
            foreach ($points as $formatPoint) {
                $projectKey = 5;
                foreach ($formatPoint as $projectPoint) {
                    
                    // set tasks
                    foreach ($projectPoint['tasks'] as $task) {
                        $excel->setValue("A{$indexTask}", $projectPoint['employee']['name']);
                        $excel->setValue("B{$indexTask}", $projectPoint['employee']['employee_id'] . ' - ' . count($projectPoint['tasks']) . ' - ' . $indexing);
                        $excel->setValue("C{$indexTask}", $projectPoint['employee']['position']['name']);
                        $excel->setValue("D{$indexTask}", $projectPoint['project_name']);
                        $excel->setValue("E{$indexTask}", $task['name']);
                        $excel->setValue("F{$indexTask}", $projectPoint['point']);
                        $excel->setValue("G{$indexTask}", $projectPoint['additional_point']);
                        $excel->setValue("H{$indexTask}", $projectPoint['total_point']);
    
                        $indexTask++;
                    }

                    $indexing++;
                    $projectKey++;
                }
            }

            $excel->autoSize(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']);

            $excel->save(public_path('point.xlsx'));
            
            return generalResponse(
                message: 'success',
                error: false,
                data: [
                    'total_project' => $totalProject,
                    'point' => $points,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    protected function getAllEmployeeIdForPoint(): array
    {
        $employees = $this->repo->list(
            select: 'id',
            where: "status NOT IN (". Status::Inactive->value .")"
        );

        return collect($employees)->pluck('id')->toArray();
    }

    protected function formatPointQueryDate(array $payload): array
    {
        $start = date('Y-m-d', strtotime($payload['start_date']));
        $end = date('Y-m-d', strtotime($payload['end_date']));

        return [
            'start' => $start,
            'end' => $end
        ];
    }

    public function setDefaultPeriodQueryForPoint(string $where = ''): string
    {
        $now = Carbon::now();

        $start = $now->copy()->subMonthNoOverflow()->day(24);
        $end = $now->copy()->day(23);

        $where .= "DATE(created_at) BETWEEN {$start} AND {$end}";

        return $where;
    }
}