<?php

namespace App\Repository;

use App\Models\Menu;

class MenuRepository {
    private $model;

    public function __construct()
    {
        $this->model = new Menu();
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
        array $whereHas = []
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

        if ($whereHas) {
            foreach ($whereHas as $wh) {
                $query->whereHas($wh['relation'], function ($q) use ($wh) {
                    $q->whereRaw($wh['query']);
                });
            }
        }
        
        return $query->skip($page)->take($itemsPerPage)->get();
    }
}