<?php

namespace Modules\Inventory\Services;

use App\Enums\ErrorCode\Code;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Hrd\Models\Employee;
use Modules\Inventory\Models\CustomInventory;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Repository\CustomInventoryRepository;
use Modules\Inventory\Repository\EmployeeInventoryItemRepository;
use Modules\Inventory\Repository\EmployeeInventoryMasterRepository;
use Modules\Inventory\Repository\InventoryItemRepository;

class EmployeeInventoryMasterService {
    private $repo;

    private $inventoryItemRepo;

    private $customInventoryRepo;

    private $employeeInventoryItemRepo;

    /**
     * Construction Data
     */
    public function __construct()
    {
        $this->repo = new EmployeeInventoryMasterRepository;

        $this->employeeInventoryItemRepo = new EmployeeInventoryItemRepository;

        $this->inventoryItemRepo = new InventoryItemRepository;

        $this->customInventoryRepo = new CustomInventoryRepository;
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

            $paginated = collect((object) $paginated)->map(function ($item) {
                $itemGroupsRaw = collect((object)  $item->items)->groupBy('inventory_source_id');
                $itemGroups = [];
                foreach ($itemGroupsRaw as $group => $raw) {
                    foreach ($raw as $rawItem) {
                        $itemGroups[$group][] = [
                            'display_image' => $rawItem->inventory->inventory->display_image,
                            'code' => $rawItem->inventory->inventory_code,
                            'name' => $rawItem->inventory->inventory->name,
                            'type' => $raw[0]['inventory_source'],
                        ];
                    }
                }

                return [
                    'id' => $item->id,
                    'employee' => $item->employee->name,
                    'total_items' => $item->items->count(),
                    'items' => $itemGroups,
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

    public function removeAllDuplicate(array $array)
    {
        // Serialize each sub-array for easy comparison
        $serializedArray = array_map('serialize', $array);

        // Count the occurrences of each serialized item
        $counts = array_count_values($serializedArray);

        // Filter out items that occur more than once
        $uniqueSerializedArray = array_filter($serializedArray, function ($item) use ($counts) {
            return $counts[$item] === 1;
        });

        // Unserialize to convert back to the original format
        return array_map('unserialize', $uniqueSerializedArray);
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
            $data = $this->repo->show(
                $uid,
                'employee_id, custom_inventory_id,id',
                [
                    'employee:id,name',
                    'items:id,employee_inventory_master_id,inventory_item_id,inventory_status',
                    'items.inventory:id,inventory_id,inventory_code',
                    'items.inventory.inventory:id,name'
                ]
            );

            // format inventories
            $customInventories = [];
            $rawCustomInventories = [];
            if ($data->custom_inventory_id) {
                $where = "type = 'pcrakitan'";
                $currentCustom = "(" . implode(',', $data->custom_inventory_id) . ")";
                $where .= ' and id in ' . $currentCustom;

                $dataCustomInventory = $this->customInventoryRepo->list(
                    'id,uid,name',
                    $where,
                    [
                        'items:id,custom_inventory_id,inventory_id',
                        'items.inventory:id,inventory_id,inventory_code',
                        'items.inventory.inventory:id,name'
                    ]
                );

                foreach ($dataCustomInventory as $itemCustom) {
                    foreach ($itemCustom->items as $customDetail) {
                        $rawCustomInventories[] = [
                            'title' => $customDetail->inventory->inventory->name,
                            'value' => $customDetail->inventory_id,
                        ];
                    }
                }

                $customInventories = $this->formatOutputCustomInventories($dataCustomInventory);
            }

            $inventories = collect((object) $data->items)->map(function ($itemInventory) {
                return [
                    'title' => $itemInventory->inventory->inventory->name,
                    'value' => $itemInventory->inventory_item_id,
                ];
            })->toArray();
            $inventories = array_merge($inventories, $rawCustomInventories);
            $inventories = $this->removeAllDuplicate($inventories);

            $data['custom_inventories'] = $customInventories;
            $data['inventories'] = $inventories;
            $data['employee_detail'] = [
                'employee_id' => $data->employee_id,
                'name' => $data->employee->name,
            ];

            unset($data['items']);
            unset($data['custom_inventory_id']);
            unset($data['employee']);
            unset($data['employee_id']);

            return generalResponse(
                'success',
                false,
                $data->toArray(),
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    protected function formatOutputCustomInventories(object $payload)
    {
        $output = [];
        foreach ($payload as $item) {
            $output[] = [
                'title' => $item->name,
                'value' => $item->uid,
                'items' => collect((object) $item->items)->map(function ($itemInventory) {
                    return [
                        'inventory_item_id' => $itemInventory->inventory->id,
                        'inventory_id' => $itemInventory->inventory->inventory_id,
                        'name' => $itemInventory->inventory->inventory->name,
                        'code' => $itemInventory->inventory->inventory_code
                    ];
                })->toArray(),
            ];
        }

        return $output;
    }

    public function getAvailableCustomInventories(string $employeeUid)
    {
        $allData = $this->repo->list('custom_inventory_id', 'custom_inventory_id is not null');
        $customInventoryIds = [];
        foreach ($allData as $employeeInventory) {
            $merge = array_merge($customInventoryIds, $employeeInventory->custom_inventory_id);
            $customInventoryIds = $merge;
        }

        $where = "type = 'pcrakitan'";
        if (!empty($customInventoryIds)) {
            $currentCustom = "(" . implode(',', $customInventoryIds) . ")";
            $where .= ' and id not in ' . $currentCustom;
        }

        $data = $this->customInventoryRepo->list(
            'id,uid,name',
            $where,
            [
                'items:id,custom_inventory_id,inventory_id',
                'items.inventory:id,inventory_id,inventory_code',
                'items.inventory.inventory:id,name'
            ]
        );

        $output = $this->formatOutputCustomInventories($data);

        return generalResponse(
            'success',
            false,
            $output
        );
    }

    public function getAvailableInventories(string $employeeUid)
    {
        // get current employee inventories
        $employeeId = getIdFromUid($employeeUid, new Employee());

        $current = $this->repo->show(
            'uid',
            '*',
            [
                'items:id,employee_inventory_master_id,inventory_item_id',
            ],
            'employee_id = ' . $employeeId
        );

        $output = [];
        $where = '';

        // get all inventory items that already registered on custom inventory
        $customInventories = $this->customInventoryRepo->list(
            'id',
            '',
            [
                'items:id,custom_inventory_id,inventory_id',
                'items.inventory:id,inventory_id,inventory_code',
            ]
        );
        $customInventoryItems = [];
        foreach ($customInventories as $customInventory) {
            foreach ($customInventory->items as $itemInventory) {
                $customInventoryItems[] = $itemInventory->inventory->id;
            }
        }

        if ($current) {
            $itemIds = collect((object) $current->items)->pluck('inventory_item_id')->toArray();

            if (count($itemIds) > 0) {
                $itemIds = "(" . implode(',', $itemIds) . ")";
                $where = "id not in {$itemIds}";
            }
        }

        if (!empty($customInventoryItems)) {
            $conditionCustomInventory = "(" . implode(',', $customInventoryItems) . ")";
            if (empty($where)) {
                $where = "id not in {$conditionCustomInventory}";
            } else {
                $where .= " and id not in {$conditionCustomInventory}";
            }
        }

        $items = $this->inventoryItemRepo->list('id,inventory_id,inventory_code', $where, ['inventory:id,name']);

        foreach ($items as $item) {
            $output[] = [
                'title' => $item->inventory->name,
                'value' => $item->id,
                'code' => $item->inventory_code,
            ];
        }

        return generalResponse(
            'success',
            false,
            $output
        );
    }

    public function updateInventory(array $data, mixed $id): array
    {
        try {
            $customInventory = $this->processCustomInventories($data);

            $inventories = $this->processInventories($data);

            return generalResponse(
                'success',
                false
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    protected function processCustomInventories(array $data)
    {
        $output = [];
        if (!empty($data['custom_inventories'])) {
            $output = collect($data['custom_inventories'])->map(function ($item) {
                return getIdFromUid($item['id'], new CustomInventory());
            })->toArray();
        }

        return $output;
    }

    protected function processInventories(array $data)
    {
        $inventories = [];
        if (!empty($data['inventories'])) {
            $inventories = collect($data['inventories'])->map(function ($item) {
                return [
                    'inventory_item_id' => $item['id'],
                    'inventory_status' => 1,
                    'inventory_source' => 'inventory',
                    'inventory_source_id' => 0
                ];
            })->toArray();
        }

        if (!empty($data['custom_inventories'])) {
            foreach ($data['custom_inventories'] as $customInventory) {
                $customItem = $this->customInventoryRepo->show(
                    $customInventory['id'],
                    'id,name',
                    [
                        'items:id,custom_inventory_id,inventory_id'
                    ]
                );

                $customItemList = collect((object) $customItem->items)->map(function ($inventory) use($customInventory) {
                    return [
                        'inventory_item_id' => $inventory->inventory_id,
                        'inventory_status' => 1,
                        'inventory_source' => 'custom',
                        'inventory_source_id' => getIdFromUid($customInventory['id'], new CustomInventory()),
                    ];
                })->toArray();

                $inventories = array_merge($inventories, $customItemList);
            }
        }

        return $inventories;
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

            $customInventory = $this->processCustomInventories($data);

            $inventories = $this->processInventories($data);

            $master = $this->repo->store([
                'employee_id' => $employeeId,
                'custom_inventory_id' => $customInventory
            ]);

            $master->items()->createMany($inventories);

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
     * Delete selected inventory
     * @param string $id
     * @param string $type
     * @param mixed $inventoryId
     * @return array
     */
    public function deleteInventory(string $id, string $type, string $inventoryCode)
    {
        DB::beginTransaction();
        try {
            $data = $this->repo->show($id, 'custom_inventory_id,employee_id,id');

            if ($type === 'custom') {
                // delete custom inventory id

            } else {
                // delete inventory item
//                $this->employeeInventoryItemRepo->delete(0, 'employee_inventory_master_id = ' . $id . " and inventory_item_id = " . $inventoryId);
            }

            DB::commit();

            return generalResponse(
                __('notification.successUpdateUserInventory'),
                false
            );
        } catch (\Throwable $th) {
            DB::rollBack();

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
        DB::beginTransaction();
        try {
            $employeeId = getIdFromUid($data['employee_id'], new Employee());

            $customInventory = $this->processCustomInventories($data);

            $inventories = $this->processInventories($data);

            $this->repo->update([
                'custom_inventory_id' => $customInventory
            ], $id);

            DB::commit();

            return generalResponse(
                'success',
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
