<?php

namespace Modules\Production\Services;

use App\Enums\ErrorCode\Code;
use App\Enums\Production\ProjectStatus;
use Modules\Production\Repository\InteractiveProjectRepository;

class InteractiveProjectService {
    private InteractiveProjectRepository $repo;

    /**
     * Construction Data
     */
    public function __construct(InteractiveProjectRepository $repo)
    {
        $this->repo = $repo;
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

            $paginated = $paginated->map(function ($item) {
                $item['project_date_text'] = date('d F Y', strtotime($item->project_date));
                $item['status_text'] = $item->status->label();
                $item['status_color'] = $item->status->color();
                $item['pic_name'] = '-';

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
            $data = $this->repo->show($uid, '*', [
                'boards'
            ]);

            $output = [
                'uid' => $data->uid,
                'name' => $data->name,
                'description' => $data->description,
                'status_raw' => $data->status->value,
                'status_text' => $data->status->label(),
                'status_color' => $data->status->color(),
                'project_date' => date('d F Y', strtotime($data->project_date)),
                'pic_names' => '-',
                'teams' => [],
                'references' => [],
                'boards' => $data->boards->map(function ($board) {
                    return [
                        'id' => $board->id,
                        'name' => $board->name,
                        'tasks' => []
                    ];
                }),
                'project_is_complete' => $data->status === ProjectStatus::Completed ? true : false,
                'permission_list' => [
                    'add_task' => true
                ]
            ];

            return generalResponse(
                'success',
                false,
                $output,
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