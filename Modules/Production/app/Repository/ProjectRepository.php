<?php

namespace Modules\Production\Repository;

use Illuminate\Database\Eloquent\Collection;
use Modules\Production\Models\Project;
use Modules\Production\Repository\Interface\ProjectInterface;

class ProjectRepository extends ProjectInterface
{
    private $model;

    private $key;

    public function __construct()
    {
        $this->model = new Project;
        $this->key = 'id';
    }

    /**
     * Get All Data
     *
     * @param  array<int, \Closure>  $whereExists  Each closure is applied as a whereExists() subquery (AND).
     * @param  array<int, \Closure>  $orWhereExists  Each closure is applied as an orWhereExists() subquery (OR).
     * @param  array<int, \Closure>  $whereGroup  Each closure is applied as a nested where() group - use it to
     *                                            combine whereExists()/orWhereExists() with OR inside one group.
     * @return Collection
     */
    public function list(
        string $select = '*',
        string $where = '',
        array $relation = [],
        array $whereHas = [],
        string $orderBy = '',
        int $limit = 0,
        array $isGetDistance = [],
        array $has = [],
        array $whereExists = [],
        array $orWhereExists = [],
        array $whereGroup = []
    ) {
        $query = $this->model->query();

        $query->selectRaw($select);

        if (! empty($where)) {
            $query->whereRaw($where);
        }

        if (count($whereHas) > 0) {
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

        if (! empty($has)) {
            foreach ($has as $hasQuery) {
                $query->has($hasQuery);
            }
        }

        if (! empty($whereGroup)) {
            foreach ($whereGroup as $group) {
                $query->where($group);
            }
        }

        if (! empty($whereExists)) {
            foreach ($whereExists as $existsCallback) {
                $query->whereExists($existsCallback);
            }
        }

        if (! empty($orWhereExists)) {
            foreach ($orWhereExists as $orExistsCallback) {
                $query->orWhereExists($orExistsCallback);
            }
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
     * @param  array<int, \Closure>  $whereExists  Each closure is applied as a whereExists() subquery (AND).
     * @param  array<int, \Closure>  $orWhereExists  Each closure is applied as an orWhereExists() subquery (OR).
     * @param  array<int, \Closure>  $whereGroup  Each closure is applied as a nested where() group - use it to
     *                                            combine whereExists()/orWhereExists() with OR inside one group.
     * @return Collection
     */
    public function pagination(
        string $select = '*',
        string $where = '',
        array $relation = [],
        int $itemsPerPage = 10,
        int $page = 1,
        array $whereHas = [],
        string $sortBy = '',
        array $has = [],
        array $whereExists = [],
        array $orWhereExists = [],
        array $whereGroup = []
    ) {
        $query = $this->model->query();

        $query->selectRaw($select);

        if (! empty($where)) {
            $query->whereRaw($where);
        }

        if (count($whereHas) > 0) {
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

        if (! empty($has)) {
            foreach ($has as $hasQuery) {
                $query->has($hasQuery);
            }
        }

        if (! empty($whereGroup)) {
            foreach ($whereGroup as $group) {
                $query->where($group);
            }
        }

        if (! empty($whereExists)) {
            foreach ($whereExists as $existsCallback) {
                $query->whereExists($existsCallback);
            }
        }

        if (! empty($orWhereExists)) {
            foreach ($orWhereExists as $orExistsCallback) {
                $query->orWhereExists($orExistsCallback);
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

    public function isExists(string $name, string $projectDate)
    {
        return $this->model->select('id')
            ->isDuplicate($name, $projectDate)
            ->exists();
    }

    /**
     * Get Detail Data
     *
     * @return Collection
     */
    public function show(
        string $uid = '',
        string $select = '*',
        array $relation = [],
        string $where = '',
        array $whereHas = []
    ) {
        $query = $this->model->query();

        $query->selectRaw($select);

        if (empty($where)) {
            $query->where('uid', $uid);
        } else {
            $query->whereRaw($where);
        }

        if (count($whereHas) > 0) {
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

        if ($relation) {
            $query->with($relation);
        }

        $data = $query->first();

        return $data;
    }

    /**
     * Store Data
     *
     * @return Collection
     */
    public function store(array $data)
    {
        return $this->model->create($data);
    }

    /**
     * Update Data
     *
     * @param  int|string  $id
     * @return Collection
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

        return $query->first();
    }

    /**
     * Delete Data
     *
     * @param  int|string  $id
     * @return Collection
     */
    public function delete(int $id)
    {
        return $this->model->whereIn('id', $id)
            ->delete();
    }

    /**
     * Bulk Delete Data
     *
     * @return Collection
     */
    public function bulkDelete(array $ids, string $key = '')
    {
        if (empty($key)) {
            $key = $this->key;
        }

        $data = $this->model->whereIn($key, $ids)->get();
        foreach ($data as $project) {
            $project->delete();
        }
    }
}
