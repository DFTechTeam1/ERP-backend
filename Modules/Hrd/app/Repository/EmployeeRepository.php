<?php

namespace Modules\Hrd\Repository;

use Illuminate\Database\Eloquent\Builder;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Repository\Interface\EmployeeInterface;

class EmployeeRepository extends EmployeeInterface
{
    private $model;
    private $key;

    /**
     * @param $model
     * @param $key
     */
    public function __construct()
    {
        $this->model = new Employee();
        $this->key = 'uid';
    }

    /**
     * Get All Data
     *
     * @param string $select
     * @param string $where
     * @param array $relation
     * @return \Illuminate\Database\Eloquent\Collection
     */
    function list(
        string $select = '*',
        string $where = "",
        array $relation = [],
        string $orderBy = '',
        string $limit = '',
        array $whereHas = [],
        array $whereIn = []
    )
    {
        $query = $this->model->query();

        $query->selectRaw($select);

        if (!empty($where)) {
            $query->whereRaw($where);
        }

        if (count($whereHas) > 0) {
            foreach ($whereHas as $wh) {
                $query->whereHas($wh['relation'], function (Builder $query) use ($wh) {
                    $query->whereRaw($wh['query']);
                });
            }
        }

        if ($whereIn) {
            $query->whereIn($whereIn['key'], $whereIn['value']);
        }

        if ($relation) {
            $query->with($relation);
        }

        if (!empty($orderBy)) {
            $query->orderByRaw($orderBy);
        }

        if (!empty($limit)) {
            $query->limit($limit);
        }

        return $query->get();
    }


    /**
     * Make paginated data
     *
     * @param string $select
     * @param string $where
     * @param array $relation
     * @param int $itemsPerPage
     * @param int $page
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function pagination(
        string $select = '*',
        string $where = "",
        array $relation = [],
        int $itemsPerPage,
        int $page,
        array $whereHas = [],
        string $orderBy = ''
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

        if (!empty($orderBy)) {
            $query->orderByRaw($orderBy);
        } else {
            $query->orderBy('updated_at', 'DESC');
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
    function show(string $uid, string $select = '*', array $relation = [], string $where = '')
    {
        $query = $this->model->query();

        $query->selectRaw($select);

        if ($relation) {
            $query->with($relation);
        }

        if (empty($where)) {
            $query->where('uid', $uid);
        } else {
            $query->whereRaw($where);
        }

        return $query->first();
    }

    /**
     * Store Data
     *
     * @param array $data
     * @return \Illuminate\Database\Eloquent\Collection
     */
    function store(array $data)
    {
        return $this->model->create($data);
    }


    /**
     * Update Data
     *
     * @param array $data
     * @param string $uid
     * @param string $where
     * @return \Illuminate\Database\Eloquent\Collection
     */
    function update(array $data, string $uid='', string $where='')
    {
        $query = $this->model->query();

        if (!empty($where)) {
            $query->whereRaw($where);
        } else {
            $query->where('uid', $uid);
        }

        $model = $query->first();
        $model->fill($data);
        $model->save();

        return $model;
    }

    /**
     * Delete Data
     *
     * @param string $uid
     * @return \Illuminate\Database\Eloquent\Collection
     */
    function delete(string $uid)
    {
        return $this->model->where('uid', $uid)->delete();
    }

    /**
     * Bulk Delete Data
     *
     * @param array $ids
     * @return \Illuminate\Database\Eloquent\Collection
     */
    function bulkDelete(array $ids, string $key = '')
    {
        if (empty($key)) {
            $key = $this->key;
        }

        return $this->model->whereIn($key, $ids)
            ->delete();
    }

}
