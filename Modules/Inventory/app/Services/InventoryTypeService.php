<?php

namespace Modules\Inventory\Services;

use App\Enums\ErrorCode\Code;
use App\Exceptions\InventoryTypeRelationFound;
use Modules\Inventory\Models\InventoryType;
use Modules\Inventory\Repository\InventoryRepository;
use Modules\Inventory\Repository\InventoryTypeRepository;
use \Illuminate\Support\Facades\DB;

class InventoryTypeService {
    private $repo;

    private $inventoryRepo;

    /**
     * Construction Data
     */
    public function __construct()
    {
        $this->repo = new InventoryTypeRepository;

        $this->inventoryRepo = new InventoryRepository();
    }

    /**
     * Import excel and store to database
     *
     * $data will have
     * File 'excel'
     * @param array $data
     * @return array
     */
    public function import(array $data): array
    {
        DB::beginTransaction();
        try {
            $data = \Maatwebsite\Excel\Facades\Excel::toArray(new \App\Imports\BrandImport, $data['excel']);

            $output = [];

            $error = [];

            foreach ($data as $value) {
                unset($value[0]);
                unset($value[1]);

                foreach (array_values($value) as $val) {
                    $check = $this->repo->show('dummy', 'id', [], "lower(name) = '" . strtolower($val[0]) . "'");

                    if (!$check) {
                        $slug = strtolower(implode('_', explode(' ', $val[0])));
                        $this->repo->store(['name' => $val[0], 'slug' => $slug]);
                    } else {
                        $error[] = $val[0] . __('global.alreadyRegistered');
                    }
                }
            }

            DB::commit();

            return generalResponse(
                __("global.importInventoryTypeSuccess"),
                false,
                [
                    'error' => $error,
                ],
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Get list of data
     *
     * @param string $select
     * @param string $where
     * @param array $relation
     *
     * @return array
     */
    public function list(
        string $select = '*',
        string $where = '',
        array $relation = [],
    ): array
    {
        try {
            $itemsPerPage = request('itemsPerPage') ?? config('app.pagination_length');;
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $search = request('search');

            if (!empty($search)) { // array
                $where = formatSearchConditions($search['filters'], $where);
            }

            $sort = "name asc";
            if (request('sort')) {
                $sort = "";
                foreach (request('sort') as $sortList) {
                    if ($sortList['field'] == 'name') {
                        $sort = $sortList['field'] . " {$sortList['order']},";
                    } else {
                        $sort .= "," . $sortList['field'] . " {$sortList['order']},";
                    }
                }

                $sort = rtrim($sort, ",");
                $sort = ltrim($sort, ',');
            }

            $paginated = $this->repo->pagination(
                $select,
                $where,
                $relation,
                $itemsPerPage,
                $page,
                [],
                $sort
            );
            $totalData = $this->repo->list('id', $where)->count();

            return generalResponse(
                'Success',
                false,
                [
                    'paginated' => $paginated,
                    'totalData' => $totalData,
                ],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function allList(
        string $select = '*',
        string $where = '',
        array $relation = [],
    )
    {
        $data = $this->repo->list($select, $where, $relation);

        return generalResponse(
            'Success',
            false,
            $data->toArray()
        );
    }

    public function datatable()
    {
        //
    }

    /**
     * Get detail data
     *
     * @param string $uid
     * @return array
     */
    public function show(string $uid): array
    {
        try {
            $data = $this->repo->show($uid, 'name,uid,id,slug');

            return generalResponse(
                'success',
                false,
                $data->toArray(),
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Store data
     *
     * @param array $data
     *
     * @return array
     */
    public function store(array $data): array
    {
        try {
            $data['slug'] = getSlug($data['name']);
            $this->repo->store($data);

            return generalResponse(
                __('global.successCreateInventoryType'),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Update selected data
     *
     * @param array $data
     * @param string $id
     * @param string $where
     *
     * @return array
     */
    public function update(
        array $data,
        string $id,
        string $where = ''
    ): array
    {
        try {
            $this->repo->update($data, $id);

            return generalResponse(
                __('global.successUpdateInventoryType'),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Delete selected data
     *
     * @param integer $id
     *
     * @return void
     */
    public function delete(int $id): array
    {
        try {
            return generalResponse(
                'Success',
                false,
                $this->repo->delete($id)->toArray(),
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Delete bulk data
     *
     * @param array $ids
     *
     * @return array
     */
    public function bulkDelete(array $ids): array
    {
        try {
            foreach($ids as $id) {
                $typeId = getIdFromUid($id, new InventoryType());

                $relation = $this->inventoryRepo->show('id', 'id', [], 'item_type = ' . $typeId);
                if ($relation) {
                    throw new InventoryTypeRelationFound();
                }
            }
            $this->repo->bulkDelete($ids, 'uid');

            return generalResponse(
                __('global.successDeleteInventoryType'),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}
