<?php

namespace Modules\Finance\Repository;

use Modules\Finance\Models\Invoice;
use Modules\Finance\Repository\Interface\InvoiceInterface;

class InvoiceRepository extends InvoiceInterface
{
    private $model;

    private $key;

    public function __construct()
    {
        $this->model = new Invoice;
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

        $query->update($data);

        return $query;
    }

    /**
     * Delete Data
     *
     * @param  int|string  $id
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function delete(string $invoiceUid)
    {
        $query = $this->model->query();

        $query->where('uid', $invoiceUid);

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