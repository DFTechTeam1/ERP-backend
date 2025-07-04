<?php

namespace Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;
use Modules\Inventory\Repository\SupplierRepository;

class SupplierService
{
    private $repo;

    /**
     * Construction Data
     */
    public function __construct()
    {
        $this->repo = new SupplierRepository;
    }

    /**
     * Import excel and store to database
     *
     * $data will have
     * File 'excel'
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
                    $check = $this->repo->show('dummy', 'id', [], "lower(name) = '".strtolower($val[0])."'");

                    if (! $check) {
                        $this->repo->store(['name' => $val[0]]);
                    } else {
                        $error[] = $val[0].__('global.alreadyRegistered');
                    }
                }
            }

            DB::commit();

            return generalResponse(
                __('global.importSupplierSuccess'),
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
     */
    public function list(
        string $select = '*',
        string $where = '',
        array $relation = []
    ): array {
        try {
            $itemsPerPage = request('itemsPerPage') ?? 2;
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $search = request('search');

            if (! empty($search)) { // array
                $where = formatSearchConditions($search['filters'], $where);
            }

            $paginated = $this->repo->pagination(
                $select,
                $where,
                $relation,
                $itemsPerPage,
                $page
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
    ) {
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
     */
    public function show(string $uid): array
    {
        try {
            $data = $this->repo->show($uid, 'name,uid,id');

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
     */
    public function store(array $data): array
    {
        try {
            $this->repo->store($data);

            return generalResponse(
                __('global.successCreateSupplier'),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Update selected data
     *
     * @param  int  $id
     */
    public function update(
        array $data,
        string $id,
        string $where = ''
    ): array {
        try {
            $this->repo->update($data, $id);

            return generalResponse(
                __('global.successUpdateSupplier'),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Delete selected data
     *
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
     */
    public function bulkDelete(array $ids): array
    {
        try {
            $this->repo->bulkDelete($ids, 'uid');

            return generalResponse(
                __('global.successDeleteSupplier'),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}
