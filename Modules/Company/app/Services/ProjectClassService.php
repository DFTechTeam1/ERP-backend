<?php

namespace Modules\Company\Services;

use App\Enums\ErrorCode\Code;
use Modules\Company\Repository\ProjectClassRepository;

class ProjectClassService {
    private $repo;

    /**
     * Construction Data
     */
    public function __construct()
    {
        $this->repo = new ProjectClassRepository;
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
            $itemsPerPage = request('itemsPerPage') ?? config('app.pagination_length');
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $search = request('search');

            if (!empty($search)) {
                $where = "lower(name) LIKE '%{$search}%'";
            }

            $select = 'id as uid,name,maximal_point,color';

            $paginated = $this->repo->pagination(
                $select,
                $where,
                $relation,
                $itemsPerPage,
                $page
            );

            $paginated = collect($paginated)->map(function ($item) {
                $item['point'] = $item->maximal_point;

                $item['maximal_point'] = $item->maximal_point . " " . __('global.point');

                return $item;
            });

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

    public function getAll()
    {
        $data = $this->repo->list('id,name,maximal_point');

        return generalResponse('success', false, $data->toArray());
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
                __('global.projectClassCreated'),
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
            $this->repo->update($data, $id, $where);

            return generalResponse(
                __('global.projectClassUpdated'),
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
            // validation relation
            foreach ($ids as $id) {
                $relation = $this->repo->show($id, 'id', ['project:id,project_class_id']);

                logging('relation', $relation->toArray());

                if ($relation->project) {
                    return generalResponse(
                        __('global.failedDeleteProjectClassBcsRelation'),
                        true,
                        [],
                        500,
                    );
                }
            }

            $this->repo->bulkDelete($ids, 'id');

            return generalResponse(
                __('global.successDeleteProjectClass'),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}