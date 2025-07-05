<?php

namespace App\Repository;

class UserRepository
{
    private $model;

    private $key;

    public function __construct()
    {
        $this->model = new \App\Models\User;

        $this->key = 'id';
    }

    /**
     * Get all users
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function list(
        string $select = '*',
        string $where = '',
        array $relation = [],
        array $whereRole = [],
        array $whereHasNested = []
    ) {
        $query = $this->model->query();

        $query->selectRaw($select);

        if (! empty($where)) {
            $query->whereRaw($where);
        }

        if (! empty($whereHasNested)) {
            applyNestedWhereHas($query, $whereHasNested);
        }

        if (! empty($whereRole)) {
            $query->role($whereRole);
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
        int $page,
        array $whereHas = [],
        string $orderBy = ''
    ) {
        $query = $this->model->query();

        $query->selectRaw($select);

        if (! empty($where)) {
            $query->whereRaw($where);
        }

        if ($relation) {
            $query->with($relation);
        }

        if (! empty($orderBy)) {
            $query->orderByRaw($orderBy);
        }

        return $query->skip($page)->take($itemsPerPage)->get();
    }

    /**
     * Store new user
     */
    public function store(array $data)
    {
        $query = $this->model->query();

        return $query->create($data);
    }

    public function update(
        array $data,
        string $key,
        string $value
    ) {
        return $this->model->where($key, $value)
            ->update($data);
    }

    /**
     * Bulk Delete Data
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function bulkDelete(array $ids, string $key = '')
    {
        if (empty($key)) {
            $key = $this->key;
        }

        return $this->model->whereIn($key, $ids)->delete();
    }

    public function detail(string $id = '', string $select = '*', string $where = '', array $relation = [])
    {
        $query = $this->model->query();

        $query->selectRaw($select);

        if (! empty($relation)) {
            $query->with($relation);
        }

        if (! empty($where)) {
            $query->whereRaw($where);
        }

        if (! empty($id) && empty($where)) {
            $query->where('uid', $id);
        }

        return $query->first();
    }
}
