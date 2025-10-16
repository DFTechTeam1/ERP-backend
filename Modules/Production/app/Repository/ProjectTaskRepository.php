<?php

namespace Modules\Production\Repository;

use Illuminate\Database\Eloquent\Builder;
use Modules\Production\Models\ProjectTask;
use Modules\Production\Repository\Interface\ProjectTaskInterface;

class ProjectTaskRepository extends ProjectTaskInterface
{
    private $model;

    private $key;

    public function __construct()
    {
        $this->model = new ProjectTask;
        $this->key = 'id';
    }

    public function modelClass()
    {
        return $this->model;
    }

    /**
     * Get All Data
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function list(string $select = '*', string $where = '', array $relation = [], array $whereHas = [])
    {
        $query = $this->model->query();

        $query->selectRaw($select);

        if (! empty($where)) {
            $query->whereRaw($where);
        }

        if (count($whereHas) > 0) {
            foreach ($whereHas as $wh) {
                $query->whereHas($wh['relation'], function (Builder $query) use ($wh) {
                    $query->whereRaw($wh['query']);
                });
            }
        }

        if ($relation) {
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
        string $sortBy = ''
    ) {
        $query = $this->model->query();

        $query->selectRaw($select);

        if (! empty($where)) {
            $query->whereRaw($where);
        }

        if ($relation) {
            $query->with($relation);
        }

        if (count($whereHas) > 0) {
            foreach ($whereHas as $wh) {
                $query->whereHas($wh['relation'], function (Builder $query) use ($wh) {
                    $query->whereRaw($wh['query']);
                });
            }
        }

        if (empty($sortBy)) {
            $query->orderBy('created_at', 'DESC');
        } else {
            $query->orderByRaw($sortBy);
        }

        return $query->skip($page)->take($itemsPerPage)->get();
    }

    /**
     * Get Detail Data
     *
     * @return \Modules\Production\Models\ProjectTask
     */
    public function show(string $uid, string $select = '*', array $relation = [], string $where = '')
    {
        $query = $this->model->query();

        $query->selectRaw($select);

        if (empty($where)) {
            $query->where('uid', $uid);
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

        return $query->update($data);
    }

    /**
     * Delete Data
     *
     * @param  int|string  $id
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function delete(int $id, string $where = '')
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
}
