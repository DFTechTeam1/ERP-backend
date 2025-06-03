<?php

namespace App\Repository;

use Spatie\Permission\Models\Permission;

class PermissionRepository
{
    private $model;

    public function __construct()
    {
        $this->model = new Permission;
    }

    /**
     * Get all users
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function list(
        string $select = '*',
        string $where = '',
        array $relation = []
    ) {
        $query = $this->model->query();

        $query->selectRaw($select);

        if (! empty($where)) {
            $query->whereRaw($where);
        }

        if (count($relation) > 0) {
            $query->with($relation);
        }

        return $query->get();
    }

    /**
     * Paginated data for datatable
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function pagination(
        string $select,
        string $where,
        array $relation,
        int $itemsPerPage,
        int $page
    ) {
        $query = $this->model->query();

        $query->selectRaw($select);

        if (! empty($where)) {
            $query->whereRaw($where);
        }

        if ($relation) {
            $query->with($relation);
        }

        return $query->skip($page)->take($itemsPerPage)->get();
    }

    public function store(array $data)
    {
        return $this->model->create($data);
    }

    /**
     * Detail Permission
     */
    public function show(int $id)
    {
        return $this->model->find($id);
    }
}
