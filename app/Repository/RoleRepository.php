<?php

namespace App\Repository;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class RoleRepository {
    private $model;

    private $key;

    public function __construct()
    {
        $this->model = new Role();

        $this->key = 'id';
    }

    public function show($id)
    {
        return $this->model->findById($id);
    }

    /**
     * Get all users
     *
     * @param string $select
     * @param string $where
     * @param array $relation
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function list(
        string $select = '*',
        string $where = '',
        array $relation = []
    )
    {
        $query = $this->model->query();

        $query->selectRaw($select);

        if (!empty($where)) {
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
     * @param string $select
     * @param string $where
     * @param array $relation
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function pagination(
        string $select = '*',
        string $where = "",
        array $relation = [],
        int $itemsPerPage,
        int $page
    )
    {
        $query = $this->model->query();

        $query->selectRaw($select);

        if (!empty($where)) {
            $query->whereRaw($where);
        }

        if ($relation) {
            $query->with($relation);
        }
        
        return $query->skip($page)->take($itemsPerPage)->get();
    }

    /**
     * Store new Role
     *
     * @param array $data
     */
    public function store(array $data)
    {
        return $this->model->create($data);
    }

    public function delete(int $id)
    {
        return DB::table('roles')->where('id', $id)->delete();
    }

    /**
     * Bulk Delete Data
     *
     * @param array $ids
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function bulkDelete(array $ids, string $key = '')
    {
        if (empty($key)) {
            $key = $this->key;
        }

        return DB::table('roles')->whereIn($key, $ids)->delete();
    }
}