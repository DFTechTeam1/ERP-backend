<?php

namespace Modules\Inventory\Services;

use App\Enums\ErrorCode\Code;
use Modules\Inventory\Repository\SupplierRepository;
use \Illuminate\Support\Facades\DB;

class SupplierService {
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
                        $this->repo->store(['name' => $val[0]]);
                    } else {
                        $error[] = $val[0] . __('global.alreadyRegistered');
                    }
                }
            }

            DB::commit();
    
            return generalResponse(
                __("global.importSupplierSuccess"),
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
        array $relation = []
    ): array
    {
        try {
            $itemsPerPage = request('itemsPerPage') ?? 2;
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $search = request('search');

            if (!empty($search)) {
                $where = "lower(name) LIKE '%{$search}%'";
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
     *
     * @param array $data
     * 
     * @return array
     */
    public function store(array $data): array
    {
        try {
            $this->repo->store($data);

            return generalResponse(
                __("global.successCreateSupplier"),
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
     * @param integer $id
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