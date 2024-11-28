<?php

namespace Modules\Production\Repository;

use Modules\Production\Models\Project;
use Modules\Production\Repository\Interface\ProjectInterface;

class ProjectRepository extends ProjectInterface {
    private $model;

    private $key;

    public function __construct()
    {
        $this->model = new Project();
        $this->key = 'id';
    }

    /**
     * Get All Data
     *
     * @param string $select
     * @param string $where
     * @param array $relation
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function list(string $select = '*', string $where = "", array $relation = [], array $whereHas = [], string $orderBy = '', int $limit = 0, array $isGetDistance = [])
    {
        $query = $this->model->query();

        $query->selectRaw($select);

        if (!empty($where)) {
            $query->whereRaw($where);
        }

        if (count($whereHas) > 0) {
            foreach ($whereHas as $queryItem) {
                $query->whereHas($queryItem['relation'], function ($qd) use ($queryItem) {
                    $qd->whereRaw($queryItem['query']);
                });
            }
        }

        if ($relation) {
            $query->with($relation);
        }

        if (!empty($orderBy)) {
            $query->orderByRaw($orderBy);
        }

        if ($limit > 0) {
            $query->limit($limit);
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
        int $page,
        array $whereHas = [],
        string $sortBy = ''
    )
    {
        $query = $this->model->query();

        $query->selectRaw($select);

        if (!empty($where)) {
            $query->whereRaw($where);
        }

        if (count($whereHas) > 0) {
            foreach ($whereHas as $queryItem) {
                $query->whereHas($queryItem['relation'], function ($qd) use ($queryItem) {
                    $qd->whereRaw($queryItem['query']);
                });
            }
        }

        if ($relation) {
            $query->with($relation);
        }

        if (empty($sortBy)) {
            $query->orderBy('project_date', 'DESC');
        } else {
            $query->orderByRaw($sortBy);
        }

        return $query->skip($page)->take($itemsPerPage)->get();
    }

    /**
     * Get Detail Data
     *
     * @param string $uid
     * @param string $select
     * @param array $relation
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function show(
        string $uid = '',
        string $select = '*',
        array $relation = [],
        string $where = ''
    )
    {
        $query = $this->model->query();

        $query->selectRaw($select);

        if (empty($where)) {
            $query->where("uid", $uid);
        } else {
            $query->whereRaw($where);
        }

        if ($relation) {
            $query->with($relation);
        }

        $data = $query->first();

        return $data;
    }

    /**
     * Store Data
     *
     * @param array $data
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function store(array $data)
    {
        return $this->model->create($data);
    }

    /**
     * Update Data
     *
     * @param array $data
     * @param integer|string $id
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function update(array $data, string $id = '', string $where = '')
    {
        $query = $this->model->query();

        if (!empty($where)) {
            $query->whereRaw($where);
        } else {
            $query->where('uid', $id);
        }

        $query->update($data);

        return $query->first();
    }

    /**
     * Delete Data
     *
     * @param integer|string $id
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function delete(int $id)
    {
        return $this->model->whereIn('id', $id)
            ->delete();
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

        return $this->model->whereIn($key, $ids)->delete();
    }
}
