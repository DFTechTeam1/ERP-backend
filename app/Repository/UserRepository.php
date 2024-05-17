<?php

namespace App\Repository;

class UserRepository {
    private $model;

    public function __construct()
    {
        $this->model = new \App\Models\User();    
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
     * Store new user
     *
     * @param array $data
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
    )
    {
        return $this->model->where($key, $value)
            ->update($data);
    }
}