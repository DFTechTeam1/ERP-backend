<?php

namespace Modules\Hrd\Services;

use App\Enums\ErrorCode\Code;
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
     *
     * @param integer $employeeId
     * @return \Modules\Hrd\Models\EmployeePoint
     */
    public function renderEachEmployeePoint(int $employeeId = 17, string $startDate = '', string $endDate = '')
    {
        $whereHas = [];

        if (!empty($startDate) && !empty($endDate)) {
            $whereHas[] = [
                'relation' => 'projects.project',
                'query' => "project_date BETWEEN '{$startDate}' AND '{$endDate}'"
            ];
        } else if (!empty($startDate) && empty($endDate)) {
            $whereHas[] = [
                'relation' => 'projects.project',
                'query' => "project_date >= '{$startDate}'"
            ];
        } else if (empty($startDate) && !empty($endDate)) {
            $whereHas[] = [
                'relation' => 'projects.project',
                'query' => "project_date <= '{$endDate}'"
            ];
        }

        $point = $this->repo->show(
            uid: 'id',
            select: 'id,employee_id,total_point,type',
            relation: [
                'projects:id,employee_point_id,project_id,total_point,additional_point',
                'projects.project:id,name,project_date',
                'employee:id,name,nickname,email,employee_id,position_id',
                'employee.position:id,name'
            ],
            whereHas: $whereHas,
            where: "employee_id = {$employeeId}"
        );

        // get detail information
        if ($point) {
            $relation = [];
            if ($point->type == 'production') {
                $relation = [
                    'productionTask:id,name,created_at'
                ];
            } else {
                $relation = [
                    'entertainmentTask:id,project_song_list_id,created_at',
                    'entertainmentTask.song:id,name'
                ];
            }

            $pointType = $point->type;
    
            $projects = collect($point->projects)->map(function ($item) use ($relation, $pointType) {
                $tasks = $this->pointProjectDetailRepo->list(
                    select: 'id,task_id,point_id',
                    where: "point_id = {$item->id}",
                    relation: $relation
                );
    
                $item['tasks'] = $tasks;
                $item['type'] = $pointType;
    
                return $item;
            });
    
            $point['detail_projects'] = $projects;
    
            unset($point['projects']);
        }

        return $point;
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