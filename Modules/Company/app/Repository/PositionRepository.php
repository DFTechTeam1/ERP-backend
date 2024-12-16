<?php

namespace Modules\Company\Repository;

use Modules\Company\Models\Position;
use Modules\Company\Repository\Interface\PositionInterface;

class PositionRepository extends PositionInterface {
    private $model;

    private $key;

    public function __construct()
    {
        $this->model = new Position();
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
    public function list(string $select = '*', string $where = "", array $relation = [])
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

        if(!empty($orderBy)) {
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
    public function show(string $uid, string $select = '*', array $relation = [])
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
    public function store(array $data)
    {
        return $this->model->create($data);
    }

    /**
     * Update Data
     *
     * @param array $data
     * @param string $uid
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function update(array $data, string $uid = '', string $where = '')
    {
        $query = $this->model->query();

        if (!empty($where)) {
            $query->whereRaw($where);
        } else {
            $query->where('uid', $uid);
        }

        $query->update($data);

        return $query;
    }

    /**
     * Delete Data
     *
     * @param string $uid
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function delete(string $uid)
    {
        return $this->model->where('uid', $uid)->delete();
    }

    /**
     * Bulk Delete Data
     *
     * @param array $uids
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function bulkDelete(array $uids, string $key = '')
    {
        if (empty($key)) {
            $key = $this->key;
        }

        return $this->model->whereIn($key, $uids)
            ->delete();
    }
}
