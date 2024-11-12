<?php

namespace Modules\Inventory\Services;

use App\Enums\ErrorCode\Code;
use Illuminate\Support\Facades\DB;
use Modules\Hrd\Models\Employee;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Repository\InventoryItemRepository;
use Modules\Inventory\Repository\UserInventoryMasterRepository;
use Modules\Inventory\Repository\UserInventoryRepository;
use Ramsey\Uuid\Uuid;

class UserInventoryService {
    private $repo;

    private $masterRepo;

    private $inventoryItemRepo;

    /**
     * Construction Data
     */
    public function __construct()
    {
        $this->repo = new UserInventoryRepository;

        $this->masterRepo = new UserInventoryMasterRepository();

        $this->inventoryItemRepo = new InventoryItemRepository();
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
                    'name' => $item->employee->name,
                    'user_uid' => $item->employee->uid,
                    'items' => collect($item->items)->map(function ($inventory) {
                        return [
                            'code' => $inventory->inventory->inventory_code,
                            'qrcode' => asset('storage/' . $inventory->inventory->qrcode),
                            'name' => $inventory->inventory->inventory->name,
                            'display_image' => $inventory->inventory->inventory->display_image,
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

    public function getUserInformation(string $employeeUid)
    {
        $employeeId = getIdFromUid($employeeUid, new Employee());

        $checkData = $this->masterRepo->show(
            'dummy',
            'id',
            [
                'items:id,user_inventory_master_id,inventory_id,inventory_type',
                'items.inventory:id,inventory_id',
                'items.inventory.inventory:id,name'
            ],
            'employee_id = ' . $employeeId);
        if ($checkData) {
            $items = collect((object) $checkData->items)->map(function ($item) {
                return [
                    'item_id' => $item->inventory->id,
                    'quantity' => 1,
                    'inventory_type' => $item->inventory_type
                ];
            })->toArray();
        }

        return generalResponse(
            'success',
            false,
            [
                'is_edit' => (bool) $checkData,
                'detail' => [
                    'items' => $items ?? []
                ]
            ]
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
            $data = $this->masterRepo->show($uid, 'id,uid,employee_id,total_inventory', [
                'items:id,inventory_id,quantity,user_inventory_master_id,inventory_type',
                'items.inventory:id,inventory_code,inventory_id,qrcode',
                'items.inventory.inventory:id,name',
                'employee:id,name,uid'
            ]);

            $output = [
                'uid' => $data->uid,
                'name' => $data->employee->name,
                'employee_uid' => $data->employee->uid,
                'total_inventory' => $data->total_inventory,
                'items' => collect((object) $data->items)->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->inventory->inventory->name,
                        'inventory_id' => $item->inventory->id,
                        'inventory_code' => $item->inventory->inventory_code,
                        'qrcode' => $item->inventory->qrcode ? asset('storage/' . $item->inventory->qrcode) : asset('images/noimage.png'),
                        'quantity' => $item->quantity,
                        'image' => $item->inventory->inventory->display_image
                    ];
                })->toArray()
            ];

            return generalResponse(
                'success',
                false,
                $output,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function getAvailableInventories(string $employeeUid)
    {
        $employeeId = getIdFromUid($employeeUid, new Employee());
        $currentInventory = $this->masterRepo->show('dummy', 'id', ['items:id,inventory_id,user_inventory_master_id'], 'employee_id = ' . $employeeId);
        $currentInventoryIds = collect((object) $currentInventory->items)->pluck('inventory_id')->toArray();

        $query = "(" . implode(',', $currentInventoryIds) . ")";
        $data = $this->inventoryItemRepo->list('inventory_id,inventory_code,status', 'inventory_id not in ' . $query, ['inventory:id,name']);

        $output = collect((object) $data)->map(function ($item) {
            return [
                'title' => $item->inventory->name,
                'value' => $item->inventory_code
            ];
        })->toArray();

        return generalResponse(
            'success',
            false,
            $output
        );
    }

    public function addUserInventory(array $payload, string $uid)
    {
        DB::beginTransaction();
        try {
            $master = $this->masterRepo->show($uid, 'id', ['items']);

            $master->items()->createMany(
                collect($payload['inventories'])->map(function ($item) {
                    return [
                        'inventory_id' => $item['id'],
                        'quantity' => $item['quantity']
                    ];
                })
            );

            // update total inventory
            $this->masterRepo->update(['total_inventory' => count($master->items->toArray())], $uid);

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
     * $data will have
     * string employee_id
     * array<string, string> inventories
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
                'total_inventory' => count($data['inventories'])
            ]);

            $master->items()->createMany(
                collect($data['inventories'])->map(function ($item) {
                    return [
                        'inventory_id' => $item['id'],
                        'quantity' => $item['quantity'],
                        'inventory_type' => $item['inventory_type'],
                        'custom_inventory_id' => $item['custom_inventory_id']
                    ];
                })
            );

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
     * @data will have
     * string employee_id
     * array<string, string> inventories
     * array<string,int> deleted_inventories
     *
     * @return array
     */
    public function update(
        array $data,
        string $id,
        string $where = ''
    ): array
    {
        DB::beginTransaction();
        try {
            $master = $this->masterRepo->show($id, 'id', ['items']);

            if ((isset($data['deleted_inventories'])) && (!empty($data['deleted_inventories']))) {
                $queryDelete = "(";
                $queryDelete .= implode(
                    ',',
                    collect($data['deleted_inventories'])->pluck('current_id')->toArray()
                );
                $queryDelete .= ")";
                $this->repo->delete(0, 'id in ' . $queryDelete);
            }

            $newInventory = collect($data['inventories'])->filter(function ($filter) {
                return !$filter['current_id'];
            })->map(function ($mapping) {
                return [
                    'inventory_id' => $mapping['id'],
                    'quantity' => $mapping['quantity']
                ];
            })->values()->toArray();
            $master->items()->createMany($newInventory);

            // update total inventory
            $this->masterRepo->update(['total_inventory' => count($data['inventories'])], $id);

            DB::commit();

            return generalResponse(
                __('notification.successUpdateUserInventory'),
                false,
            );
        } catch (\Throwable $th) {
            DB::rollBack();

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
