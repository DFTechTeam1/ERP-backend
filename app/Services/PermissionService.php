<?php

namespace App\Services;

use App\Repository\PermissionRepository;

class PermissionService
{
    private $repo;

    public function __construct()
    {
        $this->repo = new PermissionRepository;
    }

    public function getAll()
    {
        $data = $this->repo->list()->map(function ($item) {
            $item['name'] = str_replace('_', ' ', $item->name);

            return $item;
        })->groupBy('group')->toArray();

        return generalResponse(
            'Success',
            false,
            $data,
        );
    }

    /**
     * Paginated Permissions
     *
     * @return array
     */
    public function list()
    {
        $itemsPerPage = request('itemsPerPage') ?? config('app.pagination_length');
        $page = request('page') ?? 1;
        $page = $page == 1 ? 0 : $page;
        $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
        $search = request('search');

        $where = '';

        $paginated = $this->repo->pagination(
            'id,name',
            $where,
            [],
            $itemsPerPage,
            $page
        );

        $totalData = $this->repo->list('id', $where)->count();

        return generalResponse(
            'success',
            false,
            [
                'paginated' => $paginated,
                'totalData' => $totalData,
            ],
        );
    }
}
