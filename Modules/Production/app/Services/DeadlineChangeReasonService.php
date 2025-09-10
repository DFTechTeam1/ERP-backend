<?php

namespace Modules\Production\Services;

use Illuminate\Support\Facades\Auth;
use Modules\Production\Repository\DeadlineChangeReasonRepository;

class DeadlineChangeReasonService
{
    private $repo;

    /**
     * Construction Data
     */
    public function __construct(DeadlineChangeReasonRepository $repo)
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
            $user = Auth::user();

            $itemsPerPage = request('itemsPerPage') ?? 50;
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $search = request('search');

            if (! empty($search)) {
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

            // define actions
            $paginated = collect($paginated)->map(function ($item) use ($user) {
                $item['can_edit'] = (bool) $user->can('edit_deadline_reasons');
                $item['can_create'] = (bool) $user->can('create_deadline_reasons');
                $item['can_delete'] = (bool) $user->can('delete_deadline_reasons');

                return $item;
            })->values();

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
     */
    public function store(array $data): array
    {
        try {
            $this->repo->store($data);

            return generalResponse(
                __('notification.successCreateDueReason'),
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
                __('notification.successUpdateDueReason'),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Delete selected data
     */
    public function delete(int $id): array
    {
        try {
            $this->repo->delete($id);

            return generalResponse(
                __('notification.successDeleteDueReason'),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Delete bulk data
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
