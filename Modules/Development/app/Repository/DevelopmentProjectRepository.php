<?php

namespace Modules\Development\Repository;

use Modules\Development\Models\DevelopmentProject;
use Modules\Development\Repository\Interface\DevelopmentProjectInterface;

class DevelopmentProjectRepository extends DevelopmentProjectInterface
{
    private $model;

    private $key;

    public function __construct()
    {
        $this->model = new DevelopmentProject;
        $this->key = 'id';
    }

    /**
     * Get All Data
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function list(string $select = '*', string $where = '', array $relation = [], string $orderBy = '', array $whereHas = [])
    {
        $query = $this->model->query();

        $query->selectRaw($select);

        if (! empty($where)) {
            $query->whereRaw($where);
        }

        if ($relation) {
            $query->with($relation);
        }

        if (! empty($whereHas)) {
            foreach ($whereHas as $queryItem) {
                if (! isset($queryItem['type'])) {
                    $query->whereHas($queryItem['relation'], function ($qd) use ($queryItem) {
                        $qd->whereRaw($queryItem['query']);
                    });
                } else {
                    $query->orWhereHas($queryItem['relation'], function ($qd) use ($queryItem) {
                        $qd->whereRaw($queryItem['query']);
                    });
                }
            }
        }

        if (! empty($orderBy)) {
            $query->orderByRaw($orderBy);
        }

        return $query->get();
    }

    /**
     * Paginated data for datatable
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function pagination(
        string $select = '*',
        string $where = '',
        array $relation = [],
        int $itemsPerPage = 10,
        int $page = 1,
        array $whereHas = []
    ) {
        $query = $this->model->query();

        $query->selectRaw($select);

        if (! empty($where)) {
            $query->whereRaw($where);
        }

        if ($relation) {
            $query->with($relation);
        }

        if (! empty($whereHas)) {
            foreach ($whereHas as $queryItem) {
                if (! isset($queryItem['type'])) {
                    $query->whereHas($queryItem['relation'], function ($qd) use ($queryItem) {
                        $qd->whereRaw($queryItem['query']);
                    });
                } else {
                    $query->orWhereHas($queryItem['relation'], function ($qd) use ($queryItem) {
                        $qd->whereRaw($queryItem['query']);
                    });
                }
            }
        }

        return $query->skip($page)->take($itemsPerPage)->get();
    }

    /**
     * Get Detail Data
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function show(string $uid, string $select = '*', array $relation = [], array $whereHas = [])
    {
        $query = $this->model->query();

        $query->selectRaw($select);

        $query->where('uid', $uid);

        if ($relation) {
            $query->with($relation);
        }

        if (! empty($whereHas)) {
            foreach ($whereHas as $queryItem) {
                if (! isset($queryItem['type'])) {
                    $query->whereHas($queryItem['relation'], function ($qd) use ($queryItem) {
                        $qd->whereRaw($queryItem['query']);
                    });
                } else {
                    $query->orWhereHas($queryItem['relation'], function ($qd) use ($queryItem) {
                        $qd->whereRaw($queryItem['query']);
                    });
                }
            }
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
    public function delete(int $id)
    {
        return $this->model->whereIn('id', $id)
            ->delete();
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
