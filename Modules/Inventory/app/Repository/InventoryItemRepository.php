<?php

namespace Modules\Inventory\Repository;

use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Repository\Interface\InventoryItemInterface;

class InventoryItemRepository extends InventoryItemInterface {
    private $model;

    private $key;

    public function __construct()
    {
        $this->model = new InventoryItem();
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
     * Get Detail Data
     *
     * @param string $uid
     * @param string $select
     * @param array $relation
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function show(string $uid, string $select = '*', array $relation = [], string $where = '')
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

        return $query;
    }

    /**
     * Delete Data
     *
     * @param integer|string $id
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function delete(int $id, string $key = 'id')
    {
        return $this->model->where($key, $id)
            ->delete();
    }

    /**
     * Multiple update and create
     *
     * @param array $data
     * @param array $uniqueColumns
     * @param array $updatedColumns
     * @return void
     */
    public function upsert(
        array $data,
        array $uniqueColumns,
        array $updatedColumns
    )
    {
        return $this->model->upsert(
            $data,
            $uniqueColumns,
            $updatedColumns
        );
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
