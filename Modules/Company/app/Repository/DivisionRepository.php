<?php

namespace Modules\Company\Repository;

use Illuminate\Support\Facades\Log;
use Modules\Company\Models\Division;
use Modules\Company\Models\DivisionBackup;
use Modules\Company\Repository\Interface\DivisionInterface;

class DivisionRepository extends DivisionInterface
{
    private $model;
    private $key;

    /**
     * @param $model
     * @param $key
     */
    public function __construct()
    {
        $this->model = new DivisionBackup();
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
    function list(string $select = '*', string $where = "", array $relation = [])
    {
        $query = $this->model->query();

        $query->selectRaw($select);

        if (!empty($where)) {
            $query->whereRaw($where);
        }

        if ($relation) {
            $query->with($relation);
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
    function show(string $uid, string $select = '*', array $relation = [])
    {
        $query = $this->model->query();

        $query->selectRaw($select);

        if ($relation) {
            $query->with($relation);
        }

        $data = $query->where('uid', $uid);

        return $data->first();
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

        $query->update($data);

        return $query->get();
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
