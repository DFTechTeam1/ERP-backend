<?php

namespace Modules\Company\Services;

use App\Enums\ErrorCode\Code;
use Modules\Company\Exceptions\BranchDeleteErrorRelation;
use Modules\Company\Repository\BranchRepository;

class BranchService
{
    private $repo;

    /**
     * Construction Data
     */
    public function __construct(BranchRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Get list of data
     */
    public function list(
        string $select = '*',
        string $where = '',
        array $relation = []
    ): array {
        try {
            $itemsPerPage = request('itemsPerPage') ?? 2;
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;

            $search = request('search');

            if (! empty($search)) { // array
                $where = formatSearchConditions($search['filters'], $where);
            }

            $sort = 'name asc';
            if (request('sort')) {
                $sort = '';
                foreach (request('sort') as $sortList) {
                    if ($sortList['field'] == 'name') {
                        $sort = $sortList['field']." {$sortList['order']},";
                    } else {
                        $sort .= ','.$sortList['field']." {$sortList['order']},";
                    }
                }

                $sort = rtrim($sort, ',');
                $sort = ltrim($sort, ',');
            }

            $paginated = $this->repo->pagination(
                $select,
                $where,
                $relation,
                $itemsPerPage,
                $page,
                [],
                $sort
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
     * Get all branches
     */
    public function getAll(): array
    {
        $where = '';

        if (request('search')) {
            $search = request('search');
            $where .= "name like '%{$search}%'";
        }

        $data = $this->repo->list('id,name', $where);

        $data = collect((object) $data)->map(function ($item) {
            return [
                'value' => $item->id,
                'title' => $item->name,
            ];
        })->toArray();

        return generalResponse(
            'success',
            false,
            $data
        );
    }

    public function datatable()
    {
        //
    }

    /**
     * Get detail data
     */
    public function show(string $uid): array
    {
        try {
            $data = $this->repo->show($uid, 'id,name,short_name');

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
     */
    public function store(array $data): array
    {
        try {
            $this->repo->store($data);

            return generalResponse(
                __('notification.successCreateBranch'),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Update selected data
     */
    public function update(
        array $data,
        string $id,
        string $where = ''
    ): array {
        try {
            $this->repo->update($data, $id);

            return generalResponse(
                __('notification.successUpdateBranch'),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Delete selected data
     *
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
     * @param  array  $ids
     */
    public function bulkDelete(array $uids, string $key = ''): array
    {
        try {
            foreach ($uids as $uid) {
                $data = $this->repo->show($uid, 'id', ['employees:id,branch_id']);
                if ($data->employees->count() > 0) {
                    throw new BranchDeleteErrorRelation;
                }
            }

            $this->repo->bulkDelete($uids, $key);

            return generalResponse(
                __('global.successDeleteDivision'),
                false,
                [],
            );
        } catch (\Throwable $th) {
            return generalResponse(
                errorMessage($th),
                true,
                [],
                Code::BadRequest->value,
            );
        }
    }
}
