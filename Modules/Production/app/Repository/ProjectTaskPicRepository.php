<?php

namespace Modules\Production\Repository;

use Modules\Production\Models\ProjectTaskPic;
use Modules\Production\Repository\Interface\ProjectTaskPicInterface;

class ProjectTaskPicRepository extends ProjectTaskPicInterface
{
    private $model;

    private $key;

    public function __construct()
    {
        $this->model = new ProjectTaskPic;
        $this->key = 'id';
    }

    /**
     * Get All Data
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function list(string $select = '*', string $where = '', array $relation = [], string $orderBy = '', int $limit = 0)
    {
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

        if ($limit > 0) {
            $query->limit($limit);
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

    /**
     * Get Detail Data
     *
     * @param  string  $uid
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function show(int $id, string $select = '*', array $relation = [], string $where = '')
    {
        $query = $this->model->query();

        $query->selectRaw($select);

        if (! empty($where)) {
            $query->whereRaw($where);
        } else {
            $query->where('id', $id);
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
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function store(array $data)
    {
        return $this->model->create($data);
    }

    /**
     * Update Data
     *
     * @param  int|string  $id
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function update(array $data, string $id = '', string $where = '')
    {
        $query = $this->model->query();

        if (! empty($where)) {
            $query->whereRaw($where);
        } else {
            $query->where('uid', $id);
        }

        $query->update($data);

        return $query;
    }

    /**
     * Delete Data
     *
     * @param  int|string  $id
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function delete(int $id = 0, string $where = '')
    {
        $query = $this->model->query();

        if (empty($where)) {
            $query->where('id', $id);
        } else {
            $query->whereRaw($where);
        }

        return $query->delete();
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

    public function deleteWithCondition(string $where)
    {
        return $this->model->whereRaw($where)
            ->delete();
    }

    public function upsert(array $data, array $unique, array $updatedColumn)
    {
        return $this->model->upsert($data, $unique, $updatedColumn);
    }
}
