<?php

namespace Modules\Hrd\Repository;

use Illuminate\Support\Facades\DB;
use Modules\Hrd\Models\EmployeePoint;
use Modules\Hrd\Repository\Interface\EmployeePointInterface;

class EmployeePointRepository extends EmployeePointInterface
{
    private $model;

    private $key;

    public function __construct()
    {
        $this->model = new EmployeePoint;
        $this->key = 'id';
    }

    /**
     * Get All Data
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function list(string $select = '*', string $where = '', array $relation = [])
    {
        $query = $this->model->query();

        $query->selectRaw($select);

        if (! empty($where)) {
            $query->whereRaw($where);
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
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function show(string $uid, string $select = '*', array $relation = [], string $where = '', array $whereHas = [])
    {
        $query = $this->model->query();

        $query->selectRaw($select);

        if (! empty($where)) {
            $query->whereRaw($where);
        } else {
            $query->where('id', $uid);
        }

        if (! empty($whereHas)) {
            foreach ($whereHas as $whereRelation) {
                $query->whereHas($whereRelation['relation'], function ($query) use ($whereRelation) {
                    $query->whereRaw($whereRelation['query']);
                });
            }
        }

        if ($relation) {
            $query->with($relation);
        }

        $data = $query->first();

        return $data;
    }

    public function rawSql(string $table, string $select, array $relation = [], string $where = '', array $relationRaw = [])
    {
        $query = DB::table($table);

        if (! empty($relationRaw)) {
            foreach ($relationRaw as $relationData) {
                $query->join(
                    $relationData['table'],
                    $relationData['first'],
                    $relationData['operator'],
                    $relationData['second'],
                    isset($relationData['type']) ? $relationData['type'] : 'inner',
                );
            }
        }

        if (! empty($realtion)) {
            $query->with($relation);
        }

        $query->selectRaw($select);

        if (! empty($where)) {
            $query->whereRaw($where);
        }

        return $query->get();
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
