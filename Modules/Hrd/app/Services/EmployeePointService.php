<?php

namespace Modules\Hrd\Services;

use App\Enums\ErrorCode\Code;
use Illuminate\Support\Facades\Log;
use Modules\Hrd\Models\EmployeePoint;
use Modules\Hrd\Repository\EmployeePointProjectDetailRepository;
use Modules\Hrd\Repository\EmployeePointProjectRepository;
use Modules\Hrd\Repository\EmployeePointRepository;

class EmployeePointService {
    private $repo;

    private $pointProjectRepo;

    private $pointProjectDetailRepo;

    /**
     * Construction Data
     */
    public function __construct(
        EmployeePointRepository $repo,
        EmployeePointProjectRepository $pointProjectRepo,
        EmployeePointProjectDetailRepository $pointProjectDetailRepo
    )
    {
        $this->repo = $repo;

        $this->pointProjectRepo = $pointProjectRepo;

        $this->pointProjectDetailRepo = $pointProjectDetailRepo;
    }

    /**
     * Get list of data
     *
     * @param string $select
     * @param string $where
     * @param array $relation
     *
     * @return array
     */
    public function list(
        string $select = '*',
        string $where = '',
        array $relation = []
    ): array
    {
        try {
            $itemsPerPage = request('itemsPerPage') ?? 2;
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $search = request('search');

            if (!empty($search)) {
                $where = "lower(name) LIKE '%{$search}%'";
            }

            $paginated = $this->repo->pagination(
                $select,
                $where,
                $relation,
                $itemsPerPage,
                $page
            );
            $totalData = $this->repo->list('id', $where)->count();

            return generalResponse(
                'Success',
                false,
                [
                    'paginated' => $paginated,
                    'totalData' => $totalData,
                ],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get detail employee point
     * Step to produce:
     * 1. Search employee_id in the employee_points table
     * 2. Search detail point projects in the empoloyee_point_projects by passing employee_points.id
     * 3. Search the detail of tasks in the employee_point_project_details by passing employee_point_projects.id
     * 4. Get the detail of task based on employee_points.type production or entertainment
     *
     * @param integer $employeeId
     * @return \Modules\Hrd\Models\EmployeePoint
     */
    public function renderEachEmployeePoint(int $employeeId = 17, string $startDate = '', string $endDate = '')
    {
        $data = $this->repo->show(
            uid: 'string',
            select: 'id,employee_id,type',
            where: "employee_id = {$employeeId}",
        );

        $totalPoint = 0;
        $totalProject = 0;
        $taskDetail = [];

        if ($data) {
            $relation = [
                'project:id,name AS project_name',
                'details:id,point_id,task_id',
                'details.productionTask:id,name,created_at'
            ];
            if ($data->type != 'production') {
                $relation = [
                    'project:id,name AS project_name',
                    'details:id,point_id,task_id',
                    'details.entertainmentTask:id,project_song_list_id,created_at',
                    'details.entertainmentTask.song:id,name'
                ];
            }
            $details = $this->pointProjectRepo->list(
                select: 'id,employee_point_id,project_id,total_point AS total_point_per_project,additional_point',
                where: "employee_point_id = {$data->id}",
                relation: $relation,
                whereHas: [
                    ['relation' => 'project', 'query' => "project_date BETWEEN '{$startDate}' AND '{$endDate}'"]
                ]
            );

            $data['point_details'] = $details;
            $totalPoint = collect($details)->pluck('total_point_per_project')->sum();
            $totalProject = $details->count();
            $taskDetail = [];
            foreach ($details as $detail) {
                $outputTasks = [];
                foreach ($detail->details as $task) {
                    if ($data->type == 'production') {
                        $outputTasks[] = [
                            'name' => $task->productionTask->name,
                            'assigned_at' => date('d F Y, H:i', strtotime($task->productionTask->created_at))
                        ];
                    } else {
                        $outputTasks[] = [
                            'name' => $task->entertainmentTask->song->name,
                            'assigned_at' => date('d F Y, H:i', strtotime($task->entertainmentTask->created_at))
                        ];
                    }
                }

                $taskDetail[] = [
                    'project_name' => $detail->project->project_name,
                    'point' => $detail->total_point_per_project - $detail->additional_point,
                    'additional_point' => $detail->additional_point,
                    'total_point' => $detail->total_point_per_project,
                    'tasks' => $outputTasks
                ];
            }
        }

        // $dummy = $this->repo->rawSql(
        //     table: 'employee_points AS es',
        //     select: "
        //         es.employee_id, es.total_point AS total_point_employee, es.type, es.id,
        //         epp.project_id, epp.total_point AS total_point_per_project, epp.additional_point, epp.id as point_id,
        //         p.name AS project_name
        //     ",
        //     where: "employee_id = {$employeeId} AND p.project_date BETWEEN '{$startDate}' AND '{$endDate}'",
        //     relationRaw: [
        //         'projects' => function ($query) use($startDate, $endDate) {
        //             $query->selectRaw('id,employee_point_id,project_id,total_point,additional_point')
        //                 ->with(['project:id,name,project_date'])
        //                 ->whereHas('project', function ($q) use ($startDate, $endDate) {
        //                     $q->whereBetween('project_date', [$startDate, $endDate]);
        //                 });
        //         },
        //         'employee:id,name,nickname,email,employee_id,position_id',
        //         'employee.position:id,name'
        //     ],
        // );

        // $newOutput = [];
        // $totalPoint = 0;
        // $totalProject = 0;
        // foreach ($dummy as $dataDummy) {
        //     $totalPoint += $dataDummy->total_point_per_project;
        //     $totalProject += 1;

        //     // get tasks
        //     $relation = [
        //         'productionTask:id,name,created_at'
        //     ];
        //     if ($dataDummy->type != 'production') {
        //         $relation = [
        //             'entertainmentTask:id,project_song_list_id,created_at',
        //             'entertainmentTask.song:id,name'
        //         ];
        //     }
        //     $tasks = $this->pointProjectDetailRepo->list(
        //         select: 'id,task_id,point_id',
        //         where: "point_id = {$dataDummy->point_id}",
        //         relation: $relation
        //     );
        //     $outputTasks = [];
        //     if ($dataDummy->type == 'production') {
        //         $outputTasks = collect((object) $tasks)->map(function ($task) {
        //             return [
        //                 'name' => $task->productionTask->name,
        //                 'assigned_at' => date('d F Y', strtotime($task->productionTask->created_at))
        //             ];
        //         })->toArray();
        //     } else {
        //         $outputTasks = collect((object) $tasks)->map(function ($task) {
        //             return [
        //                 'name' => $task->entertainmentTask->song->name,
        //                 'assigned_at' => date('d F Y', strtotime($task->entertainmentTask->created_at))
        //             ];
        //         })->toArray();
        //     }

        //     $newOutput[] = [
        //         'project_name' => $dataDummy->project_name,
        //         'point' => $dataDummy->total_point_per_project - $dataDummy->additional_point,
        //         'additional_point' => $dataDummy->additional_point,
        //         'total_point' => $dataDummy->total_point_per_project,
        //         'tasks' => $outputTasks
        //     ];
        // }

        return [
            'total_point' => $totalPoint,
            'total_project' => $totalProject,
            'task_details' => $taskDetail
        ];
    }

    public function datatable()
    {
        //
    }

    /**
     * Get detail data
     *
     * @param string $uid
     * @return array
     */
    public function show(string $uid): array
    {
        try {
            $data = $this->repo->show($uid, 'name,uid,id');

            return generalResponse(
                'success',
                false,
                $data->toArray(),
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Store data
     *
     * @param array $data
     *
     * @return array
     */
    public function store(array $data): array
    {
        try {
            $this->repo->store($data);

            return generalResponse(
                'success',
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Update selected data
     *
     * @param array $data
     * @param string $id
     * @param string $where
     *
     * @return array
     */
    public function update(
        array $data,
        string $id,
        string $where = ''
    ): array
    {
        try {
            $this->repo->update($data, $id);

            return generalResponse(
                'success',
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Delete selected data
     *
     * @param integer $id
     *
     * @return void
     */
    public function delete(int $id): array
    {
        try {
            return generalResponse(
                'Success',
                false,
                $this->repo->delete($id)->toArray(),
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Delete bulk data
     *
     * @param array $ids
     *
     * @return array
     */
    public function bulkDelete(array $ids): array
    {
        try {
            $this->repo->bulkDelete($ids, 'uid');

            return generalResponse(
                'success',
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}
