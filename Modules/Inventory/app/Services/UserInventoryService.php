<?php

namespace Modules\Inventory\Services;

use App\Enums\ErrorCode\Code;
use Illuminate\Support\Facades\DB;
use Modules\Hrd\Models\Employee;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Repository\UserInventoryMasterRepository;
use Modules\Inventory\Repository\UserInventoryRepository;
use Ramsey\Uuid\Uuid;

class UserInventoryService {
    private $repo;

    private $masterRepo;

    /**
     * Construction Data
     */
    public function __construct()
    {
        $this->repo = new UserInventoryRepository;

        $this->masterRepo = new UserInventoryMasterRepository();
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

            $paginated = $this->masterRepo->pagination(
                $select,
                $where,
                $relation,
                $itemsPerPage,
                $page
            );
            $totalData = $this->masterRepo->list('id', $where)->count();

            $paginated = collect($paginated)->map(function ($item) {
                return [
                    'uid' => $item->uid,
                    'total' => $item->total_inventory,
                    'user' => [
                        'name' => $item->employee->name,
                        'uid' => $item->employee->uid,
                    ],
                    'items' => collect($item->items)->map(function ($inventory) {
                        return [
                            'code' => $inventory->inventory->inventory_code,
                            'qrcode' => asset('storage/' . $inventory->inventory->qrcode),
                            'name' => $inventory->inventory->inventory->name,
                        ];
                    }),
                    'created_at' => date('d F Y', strtotime($item->created_at)),
                ];
            });

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

    public function addUserInventory(array $payload, string $uid)
    {
        DB::beginTransaction();
        try {
            $master = $this->masterRepo->show($uid, 'id', ['items']);

            // combine current items with the new one
            // let the other function handle the rest

            $this->addItem($payload, $master);

            DB::commit();

            return generalResponse(
                __('notification.successAddUserInventory'),
                false
            );
        } catch (\Throwable $th) {
            DB::rollBack();

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
        DB::beginTransaction();
        try {
            $employeeId = getIdFromUid($data['employee_id'], new Employee());

            $master = $this->masterRepo->store([
                'employee_id' => $employeeId,
                'total_inventory' => collect($data['inventories'])->pluck('quantity')->sum()
            ]);

            $this->addItem($data['inventories'], $master);

            DB::commit();

            return generalResponse(
                __('notification.userInventoryStored'),
                false,
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * @param array<string, mixed> $inventories
     * @param object $master
     * @return void
     */
    public function addItem(array $inventories, object $master)
    {
        $payload = collect($inventories)->map(function ($item) {
            return [
                'inventory_id' => $item['id'],
                'quantity' => $item['quantity'],
                'user_inventory_master_id' => $item['user_inventory_master_id'] ?? 0,
            ];
        });

        // remove duplicate
        $payload = collect($payload)->groupBy('inventory_id')
            ->map(function($item) {
                return [
                    'quantity' => $item->sum('quantity'),
                    'inventory_id' => $item->first()['inventory_id'],
                    'user_inventory_master_id' => $item->first()['user_inventory_master_id'],
                ];
            })->values()->toArray();
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
                'success',
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
                'success',
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}
