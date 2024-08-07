<?php

namespace Modules\Inventory\Services;

use App\Enums\ErrorCode\Code;
use App\Enums\Inventory\InventoryStatus;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\Brand;
use Modules\Inventory\Models\InventoryType;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Models\Inventory;
use Modules\Inventory\Repository\InventoryItemRepository;
use Modules\Inventory\Repository\InventoryRepository;
use Modules\Inventory\Repository\InventoryTypeRepository;
use Modules\Inventory\Repository\InventoryImageRepository;
use Modules\Production\Repository\ProjectEquipmentRepository;
use Modules\Production\Repository\ProjectRepository;
use Modules\Inventory\Repository\CustomInventoryRepository;
use Modules\Inventory\Repository\CustomInventoryDetailRepository;
use Modules\Company\Repository\SettingRepository;
use Modules\Company\Services\SettingService;
use Carbon\Carbon;

class InventoryService {
    private $repo;

    private $inventoryTypeRepo;

    private $inventoryItemRepo;

    private $inventoryImageRepo;

    private $projectEquipmentRepo;

    private $projectRepo;

    private $customItemRepo;

    private $customItemDetailRepo;

    private $settingRepo;

    private $settingService;

    private string $imageFolder = 'inventory';

    private string $buildSeriesPrefix = 'CB-';

    /**
     * Construction Data
     */
    public function __construct()
    {
        $this->repo = new InventoryRepository;

        $this->inventoryTypeRepo = new InventoryTypeRepository;

        $this->inventoryItemRepo = new InventoryItemRepository;

        $this->inventoryImageRepo = new InventoryImageRepository;

        $this->projectEquipmentRepo = new ProjectEquipmentRepository;

        $this->projectRepo = new ProjectRepository;

        $this->customItemRepo = new CustomInventoryRepository;

        $this->customItemDetailRepo = new CustomInventoryDetailRepository;

        $this->settingRepo = new SettingRepository;

        $this->settingService = new SettingService;
    }

    public function requestEquipmentList()
    {
        try {
            $select = 'id,name,uid,project_date';
            $where = '';
            $relation = ['equipments:id,project_id,inventory_id,qty,status'];

            $itemsPerPage = request('itemsPerPage') ?? config('app.pagination_length');
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $search = request('search');
            $whereHas = [
                [
                    'relation' => 'equipments',
                    'query' => 'status = ' . \App\Enums\Production\RequestEquipmentStatus::Requested->value,
                ]
            ];

            if (!empty($search)) { // array
                if (!empty($search['name']) && empty($where)) {
                    $name = strtolower($search['name']);
                    $where = "LOWER(name) LIKE '%{$name}%'";
                } else if (!empty($search['name']) && !empty($where)) {
                    $name = strtolower($search['name']);
                    $where .= " AND LOWER(name) LIKE '%{$name}%'";
                }
            }

            $paginated = $this->projectRepo->list(
                $select,
                $where,
                $relation,
                $whereHas
            );

            $paginated = collect($paginated)->map(function ($item) {
                return [
                    'uid' => $item->uid,
                    'project_date' => date('d F Y', strtotime($item->project_date)),

                    'name' => $item->name,
                    'equipment_total' => count($item->equipments),
                ];
            })->all();

            return generalResponse(
                'Success',
                false,
                $paginated,
            );
        } catch (\Throwable $th) {
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
            $itemsPerPage = request('itemsPerPage') ?? config('app.pagination_length');
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $search = request('search');
            $whereHas = [];

            if (!empty($search)) { // array
                if (!empty($search['name']) && empty($where)) {
                    $name = strtolower($search['name']);
                    $where = "LOWER(name) LIKE '%{$name}%'";
                } else if (!empty($search['name']) && !empty($where)) {
                    $name = strtolower($search['name']);
                    $where .= " AND LOWER(name) LIKE '%{$name}%'";
                }

                if (
                    (
                        (!empty($search['stockCondition'])) &&
                        !empty($search['quantity'])
                    )
                ) {
                    if ($search['stockCondition'] == 'more_than') {
                        $marker = '>';
                    } else if ($search['stockCondition'] == 'less_than') {
                        $marker = '<';
                    } else {
                        $marker = '=';
                    }
                    $qty = $search['quantity'];

                    if (empty($where) && !empty($qty)) {
                        $where = "stock {$marker} {$qty}";
                    } else if ((!empty($where) && !empty($qty))) {
                        $where .= " AND stock {$marker} {$qty}";
                    }
                }

                if (!empty($search['brand'])) {
                    $whereHas[] = [
                        'relation' => 'brand',
                        'query' => "LOWER(name) LIKE '%{$search['brand']}%'",
                    ];
                }

                if (!empty($search['yearOfPurchase']) && empty($where)) {
                    $where = "year_of_purchase = {$search['yearOfPurchase']}";
                } elseif (!empty($search['yearOfPurchase']) && !empty($where)) {
                    $where .= " AND year_of_purchase = {$search['yearOfPurchase']}";
                }

                if (
                    (
                        (!empty($search['purchasePriceCondition'])) &&
                        !empty($search['purchasePrice'])
                    )
                ) {
                    if ($search['purchasePriceCondition'] == 'more_than') {
                        $marker = '>';
                    } else if ($search['purchasePriceCondition'] == 'less_than') {
                        $marker = '<';
                    } else {
                        $marker = '=';
                    }
                    $qty = $search['purchasePrice'];

                    if (empty($where) && !empty($qty)) {
                        $where = "purchase_price {$marker} {$qty}";
                    } else if ((!empty($where) && !empty($qty))) {
                        $where .= " AND purchase_price {$marker} {$qty}";
                    }
                }

                if (!empty($search['warranty']) && empty($where)) {
                    $where = "warranty = {$search['warranty']}";
                } elseif (!empty($search['warranty']) && !empty($where)) {
                    $where .= " AND warranty = {$search['warranty']}"; 
                }
            }

            $paginated = $this->repo->pagination(
                $select,
                $where,
                $relation,
                $itemsPerPage,
                $page,
                $whereHas
            );

            $inventoryStatuses = InventoryStatus::cases();

            $paginated = collect($paginated)->map(function ($item) use ($inventoryStatuses) {
                $item['stock'] = count($item->items);
                $unit = $item->unit ? $item->unit->name : '';

                $locationGroup = collect($item->items)->groupBy('current_location');
                $location = [];
                foreach ($locationGroup as $locationId => $loc) {
                    $location[] = [
                        'text' => count($locationGroup[$locationId]) . ' ' . $locationGroup[$locationId][0]['location'],
                        'color' => $locationGroup[$locationId][0]['location_badge']

                    ];

                }

                return [
                    'uid' => $item->uid,
                    'name' => $item->name,
                    'stock' => count($item->items) . ' ' . $unit,
                    'brand' => $item->brand ? $item->brand->name : '-',
                    'image' => $item->image ? asset("storage/{$this->imageFolder}/{$item->image->image}") : asset('images/noimage.png'),
                    'year_of_purchase' => $item->year_of_purchase ?? '-',
                    'purchase_price' => $item->purchase_price ? config('company.currency') . ' ' . number_format($item->purchase_price, 0, config('company.pricing_divider'), config('company.pricing_divider')) : '-',
                    'items' => collect($item->items)->map(function ($inventoryItem) {
                        return [
                            'inventory_code' => $inventoryItem->inventory_code,
                            'status' => $inventoryItem->status_text,
                        ];
                    }),
                    'locations' => $location,
                    'warranty' => $item->warranty ? $item->warranty . ' ' . __('global.year') : '-',
                ];
            });

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

    public function getItemListForCustomBuild()
    {
        $where = '';

        if (!empty(request('search'))) {
            $search = strtolower(request('search'));
            $where = "lower(name) like '%{$search}%'";
        }

        $currentCustomData = $this->customItemRepo->list('id', '', ['items:id,inventory_id,custom_inventory_id,qty']);

        $data = $this->repo->list('id,uid,name,stock', $where, [], 'warehouse_id DESC');

        $data = collect($data)->map(function ($item) use ($currentCustomData) {
            $item['active'] = false;

            // reduce stock
            foreach ($currentCustomData as $custom) {
                foreach ($custom->items as $inventory) {
                    if ($inventory->inventory_id == $item->id) {
                        $item['stock'] = $item['stock'] - $inventory->qty;
                    }
                }
            }

            return $item;
        });

        return generalResponse(
            'success',
            false,
            $data->toArray(),
        );
    }

    public function getAll()
    {
        $data = $this->repo->list('id,uid as value,name as title', '', ['items', 'image']);

        $data = collect($data)->map(function ($item) {
            $locationGroup = collect($item->items)->groupBy('current_location');
            $location = [];
            foreach ($locationGroup as $locationId => $loc) {
                $location[] = [
                    'text' => count($locationGroup[$locationId]) . ' ' . $locationGroup[$locationId][0]['location'],
                    'color' => $locationGroup[$locationId][0]['location_badge']
                ];

            }

            $image = $item->image ? asset('storage/inventory/' . $item->image->image) : asset('images/noimage.png');

            return [
                'value' => $item->value,
                'title' => $item->title,
                'location' => $location,
                'image' => $image,
            ];
        })->all();

        return generalResponse(
            'success',
            false,
            $data,
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
            $data = $this->repo->show(
                $uid,
                'id,uid,name,item_type,brand_id,supplier_id,description,year_of_purchase,unit_id,purchase_price,warranty,stock,created_by,updated_by,updated_at,created_at,warehouse_id',
                [
                    'brand:id,uid,name',
                    'unit:id,uid,name',
                    'supplier:id,uid,name',
                    'items:id,inventory_id,inventory_code,status,current_location,user_id',
                    'items.employee:id,uid,name',
                    'items.employee:id,uid',
                    'images:id,image,inventory_id',
                    'itemTypeRelation:id,uid,name'
                ]
            );

            $unit = '';
            if ($data->unit) {
                $unit = [
                    'uid' => $data->unit->uid,
                    'name' => $data->unit->name,
                ];
            }

            $itemType = '';
            if ($data->itemTypeRelation) {
                $itemType = [
                    'uid' => $data->itemTypeRelation->uid,
                    'name' => $data->itemTypeRelation->name,
                ];
            }

            $brand = '';
            if ($data->brand) {
                $brand = [
                    'uid' => $data->brand->uid,
                    'name' => $data->brand->name,
                ];
            }

            $supplier = '';
            if ($data->supplier) {
                $supplier = [
                    'uid' => $data->supplier->uid,
                    'name' => $data->supplier->name,
                ];
            }

            $warehouses = \App\Enums\Inventory\Warehouse::cases();
            foreach ($warehouses as $warehouse) {
                if ($warehouse->value == $data->warehouse_id) {
                    $warehouseText = $warehouse->label();
                    $warehouseColor = $warehouse->color();
                }
            }

            $out = [
                'uid' => $data->uid,
                'name' => $data->name,
                'brand' => $brand,
                'unit' => $unit,
                'supplier' => $supplier,
                'display_image' => $data->display_image,
                'images' => collect($data->images)->map(function ($itemImage) {
                    $image = asset("storage/{$this->imageFolder}/{$itemImage->image}");

                    return [
                        'id' => $itemImage->id,
                        'image' => $image,
                    ];
                })->all(),
                'stock' => $data->stock,
                'item_type' => $itemType,
                'description' => $data->description,
                'year_of_purchase' => $data->year_of_purchase,
                'purchase_price' => $data->purchase_price ? config('company.currency') . ' ' . number_format($data->purchase_price, 0, config('company.pricing_divider'), config('company.pricing_divider')) : '-',
                'price_raw' => $data->purchase_price ? $data->purchase_price : '',
                'last_update' => date('d F Y H:i', strtotime($data->updated_at)),
                'warehouse_id' => $data->warehouse_id,
                'warehouse_text' => $warehouseText ?? '',
                'warehouse_color' => $warehouseColor ?? '',
                'warranty' => $data->warranty,
                'items' => collect($data->items)->map(function ($item) {
                    return [
                        'inventory_code' => $item->inventory_code,
                        'status' => $item->status,
                        'status_text' => $item->status_text,
                        'current_location' => $item->current_location,
                        'location' => $item->location,
                        'id' => $item->id,
                        'user_id' => $item->employee ? $item->employee->uid : null,
                        'user' => $item->employee ? $item->employee->name : null,
                        'qrcode' => createQr($item->inventory_code),
                    ];
                })
            ];

            return generalResponse(
                'success',
                false,
                $out,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function updateBuildInventory(array $data, string $uid): array
    {
        DB::beginTransaction();
        try {
            $data['default_request_item'] = $data['default_request_item'] ? true : false;

            // override default request item if needed
            if ($data['default_request_item']) {
                $this->customItemRepo->update([
                    'default_request_item' => 0,
                ], '', "default_request_item = 1");
            }
            
            $this->customItemRepo->update(
                collect($data)->except(['inventories'])->toArray(),
                $uid
            );

            $defaultItem = $this->customItemRepo->list('id', 'default_request_item = 1')->count();
            $this->settingService->storeVariables([
                'have_default_request_item' => $defaultItem > 0 ? 1 : 0
            ]);

            $itemData = $this->customItemRepo->show($uid);

            foreach ($data['inventories'] as $inventory) {
                $inventoryData = $this->repo->show($inventory['uid'], 'id,purchase_price');

                $totalPrice = $inventoryData->purchase_price * $inventory['stock'];

                if (isset($inventory['current_id'])) {
                    $this->customItemDetailRepo->update([
                        'qty' => $inventory['stock'],
                        'price' => $totalPrice,
                    ], '', 'id = ' .$inventory['current_id']);
                } else {
                    $itemData->items()->create([
                        'inventory_id' => $inventoryData->id,
                        'qty' => $inventory['stock'],
                        'price' => $totalPrice,
                    ]);
                }
            }

            DB::commit();

            return generalResponse(
                __('global.customInventoryUpdated'),
                false,
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    public function storeBuildInventory(array $data): array
    {
        DB::beginTransaction();
        try {
            $lengthData = $this->customItemRepo->list('id')->count();

            $data['build_series'] = $this->buildSeriesPrefix . generateSequenceNumber($lengthData + 1);

            $data['location'] = \App\Enums\Inventory\Location::InWarehouse->value;

            // override default request item if needed
            if ($data['default_request_item']) {
                $this->customItemRepo->update([
                    'default_request_item' => 0,
                ], '', 'default_request_item = 1');

                $this->settingService->storeVariables([
                    'have_default_request_item' => 1
                ]);
            }

            $item = $this->customItemRepo->store(
                collect($data)->except(['inventories'])->toArray()
            );

            $inventories = $data['inventories'];

            $item->items()->createMany(
                collect($inventories)->map(function ($item) {
                    $inventory = $this->repo->show($item['uid'], 'id,purchase_price');

                    $totalPrice = $inventory->purchase_price * $item['stock'];

                    return [
                        'inventory_id' => $inventory->id,
                        'qty' => $item['stock'],
                        'price' => $totalPrice,
                    ];
                })->toArray()
            );

            DB::commit();

            return generalResponse(
                __('global.customInventoryCreated'),
                false,
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    public function listOfBuildInventories(): array
    {
        try {
            $where = '';
            $itemsPerPage = request('itemsPerPage') ?? config('app.pagination_length');
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $search = request('search');
            $whereHas = [];

            if (!empty($search)) { // array
                if (!empty($search['name']) && empty($where)) {
                    $name = strtolower($search['name']);
                    $where = "LOWER(name) LIKE '%{$name}%'";
                } else if (!empty($search['name']) && !empty($where)) {
                    $name = strtolower($search['name']);
                    $where .= " AND LOWER(name) LIKE '%{$name}%'";
                }

                if (
                    (
                        (!empty($search['stockCondition'])) &&
                        !empty($search['quantity'])
                    )
                ) {
                    if ($search['stockCondition'] == 'more_than') {
                        $marker = '>';
                    } else if ($search['stockCondition'] == 'less_than') {
                        $marker = '<';
                    } else {
                        $marker = '=';
                    }
                    $qty = $search['quantity'];

                    if (empty($where) && !empty($qty)) {
                        $where = "stock {$marker} {$qty}";
                    } else if ((!empty($where) && !empty($qty))) {
                        $where .= " AND stock {$marker} {$qty}";
                    }
                }

                if (!empty($search['brand'])) {
                    $whereHas[] = [
                        'relation' => 'brand',
                        'query' => "LOWER(name) LIKE '%{$search['brand']}%'",
                    ];
                }

                if (!empty($search['yearOfPurchase']) && empty($where)) {
                    $where = "year_of_purchase = {$search['yearOfPurchase']}";
                } elseif (!empty($search['yearOfPurchase']) && !empty($where)) {
                    $where .= " AND year_of_purchase = {$search['yearOfPurchase']}";
                }

                if (
                    (
                        (!empty($search['purchasePriceCondition'])) &&
                        !empty($search['purchasePrice'])
                    )
                ) {
                    if ($search['purchasePriceCondition'] == 'more_than') {
                        $marker = '>';
                    } else if ($search['purchasePriceCondition'] == 'less_than') {
                        $marker = '<';
                    } else {
                        $marker = '=';
                    }
                    $qty = $search['purchasePrice'];

                    if (empty($where) && !empty($qty)) {
                        $where = "purchase_price {$marker} {$qty}";
                    } else if ((!empty($where) && !empty($qty))) {
                        $where .= " AND purchase_price {$marker} {$qty}";
                    }
                }

                if (!empty($search['warranty']) && empty($where)) {
                    $where = "warranty = {$search['warranty']}";
                } elseif (!empty($search['warranty']) && !empty($where)) {
                    $where .= " AND warranty = {$search['warranty']}"; 
                }
            }

            $paginated = $this->customItemRepo->pagination(
                '*',
                $where,
                ['items.inventory:id,uid,name'],
                $itemsPerPage,
                $page,
                $whereHas
            );

            $locations = \App\Enums\Inventory\Location::cases();

            $paginated = collect($paginated)->map(function ($item) use ($locations) {
                $location = '-';
                foreach ($locations as $loc) {
                    if ($loc->value == $item->location) {
                        $location = $loc->label();
                    }
                }

                return [
                    'uid' => $item->uid,
                    'name' => $item->name,
                    'updated' => Carbon::parse($item->updated_at)->diffForHumans(),
                    'default_request_item' => $item->default_request_item,
                    'total_price' => number_format(collect($item->items)->pluck('price')->sum(), 2, '.'),
                    'total_items' => $item->items->count(),
                    'build_series' => $item->build_series,
                    'location' => $location,
                ];
            })->toArray();

            $totalData = $this->customItemRepo->list('id', $where)->count();

            $totalPagination = ceil($totalData / config('app.pagination_length'));

            return generalResponse(
                'Success',
                false,
                [
                    'paginated' => $paginated,
                    'totalData' => $totalData,
                    'totalPagination' => $totalPagination,
                ],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function detailCustomInventory(string $uid)
    {
        $data = $this->customItemRepo->show($uid, '*', ['items.inventory:id,uid,name']);

        return generalResponse(
            'success',
            false,
            $data->toArray(),
        );
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
        $imageNames = [];

        DB::beginTransaction();
        try {
            if ((isset($data['supplier_id'])) && (!empty($data['supplier_id']))) {
                $data['supplier_id'] = getIdFromUid($data['supplier_id'], new Supplier());
            }
            $data['brand_id'] = getIdFromUid($data['brand_id'], new Brand()); 
            $data['unit_id'] = getIdFromUid($data['unit_id'], new Unit());

            $inventoryType = $this->inventoryTypeRepo->show($data['item_type'], 'id,slug,uid');

            $data['item_type'] = $inventoryType->id;

            $data['purchase_price'] = empty($data['purchase_price']) ? 0 : $data['purchase_price'];

            // store parent
            $inventory = $this->repo->store(collect($data)->only([
                'name',
                'item_type',
                'brand_id',
                'supplier_id',
                'unit_id',
                'description',
                'warranty',
                'year_of_purchase',
                'purchase_price',
                'stock',
                'warehouse_id'
            ])->toArray());

            // add items
            $this->addItems($inventory, $data);

            // handle image uploading
            if (isset($data['images'])) {
                foreach ($data['images'] as $image) {
                    $imageNames[] = [
                        'image' => uploadImage($image, $this->imageFolder)
                    ];
                }
    
                $inventory->images()->createMany($imageNames);
            }

            DB::commit();

            return generalResponse(
                __('global.successCreateInventory'),
                false
            );
        } catch (\Throwable $th) {
            // rollback image
            if (count($imageNames) > 0) {
                foreach ($imageNames as $imageName) {
                    deleteImage(public_path('storage/' . $this->imageFolder . '/' . $imageName));
                }
            }

            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Add more stock to each product parent
     *
     * @param array $data
     * @return array
     */
    public function addStock(array $data, string $parentUid)
    {
        DB::beginTransaction();
        try {
            $inventory = $this->repo->show(
                $parentUid, 
                'id,uid,item_type',
                [
                    'itemTypeRelation:id,slug',
                    'items:id,inventory_id'
                ]
            );

            $this->addItems($inventory, $data);

            DB::commit();

            return generalResponse(
                __('global.successAddStock'),
                false
            );
        } catch(\Throwable $th) {
            DB::rollBack();

            return generalResponse(
                errorMessage($th),
                true,
                [],
                Code::BadRequest->value,
            );
        }
    }

    protected function addItems($inventory, array $data)
    {
        // create inventory code
        // random int - SLUG item type - Inventory position - order number
        $dividerCode = '-';

        $inventoryType = $inventory->itemTypeRelation;

        $itemLoactions = [];
        $countItems = $inventory->items->count();
        foreach ($data['item_locations'] as $keyLocation => $itemLocation) {
            $inventoryCode = rand(100,900) . $dividerCode . $inventoryType->slug . $dividerCode . $countItems + 1;
            $userId = null;
            if (
                (isset($itemLocation['user_id'])) &&
                (!empty($itemLocation['user_id'])) &&
                ($itemLocation['user_id'] != 'undefined')
            ) {
                $userId = getIdFromUid($itemLocation['user_id'], new \Modules\Hrd\Models\Employee());
            }

            $itemLoactions[] = [
                'inventory_code' => $inventoryCode,
                'status' => InventoryStatus::InUse->value,
                'current_location' => $itemLocation['location'],
                'user_id' => $userId,
            ];

            $countItems++;
        }

        // store items
        $inventory->items()->createMany($itemLoactions);
    }

    public function itemList(string $uid)
    {
        try {
            $inventoryId = getIdFromUid($uid, new \Modules\Inventory\Models\Inventory());

            $data = $this->inventoryItemRepo->list(
                'id,inventory_id,inventory_code,status,current_location,user_id',
                'inventory_id = ' . $inventoryId,
                [
                    'employee:id,uid,name'
                ]
            );

            $data = collect($data)->map(function ($item) {
                return [
                    'inventory_code' => $item->inventory_code,
                    'status' => $item->status,
                    'status_text' => $item->status_text,
                    'current_location' => $item->current_location,
                    'location' => $item->location,
                    'id' => $item->id,
                    'user_id' => $item->employee ? $item->employee->uid : null,
                    'user' => $item->employee ? $item->employee->name : null,
                    'qrcode' => createQr($item->inventory_code),
                ];
            })->toArray();

            return generalResponse(
                'success',
                false,
                $data,
            );
        } catch (\Throwable $th) {
            return generalResponse(
                errorMessage($th),
                true,
                [],
                Code::BadRequest->value,
            );
        }
    }

    /**
     * Update selected data
     * Main flow is:
     * 1. Update main data (inventories table)
     * 2. Delete inventory items if exists
     * 3. Delete inventory images if exitst
     * 4. Update and Create item locations (Using Upsert method)
     * 5. Upload image if exists
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
        $imageNames = [];

        DB::beginTransaction();
        try {
            if ((isset($data['supplier_id'])) && (!empty($data['supplier_id']))) {
                $data['supplier_id'] = getIdFromUid($data['supplier_id'], new Supplier());
            }
            $data['brand_id'] = getIdFromUid($data['brand_id'], new Brand()); 
            $data['unit_id'] = getIdFromUid($data['unit_id'], new Unit());
            $inventoryId = getIdFromUid($id, new Inventory());

            $inventoryType = $this->inventoryTypeRepo->show($data['item_type'], 'id,slug,uid');

            $data['item_type'] = $inventoryType->id;

            $data['purchase_price'] = empty($data['purchase_price']) ? 0 : $data['purchase_price'];

            // create inventory code
            // random int - SLUG item type - Inventory position - order number
            $dividerCode = '-';

            // main update
            $inventory = $this->repo->update(collect($data)->only([
                'name',
                'item_type',
                'brand_id',
                'supplier_id',
                'unit_id',
                'description',
                'warranty',
                'year_of_purchase',
                'purchase_price',
                'stock'
            ])->toArray(), $id);

            $inventory = $this->repo->show($id, 'id,uid,name', ['images']);

            // delete item if exists
            if (!empty($data['deleted_item_stock'])) {
                $this->inventoryItemRepo->bulkDelete(
                    collect(
                        $data['deleted_item_stock']
                    )
                    ->pluck('id')
                    ->toArray(),
                    'id'
                );
            }

            // delete images if exits
            if (!empty($data['deleted_images'])) {
                // delete in folder
                $imageIds = collect($data['deleted_images'])
                    ->pluck('id')
                    ->toArray();
                $whereImage = 'id IN ('. implode(',', $imageIds) .')';
                $currentImages = $this->inventoryImageRepo->list('id,image', $whereImage);

                foreach ($currentImages as $currentImage) {
                    deleteImage(public_path('storage/' . $this->imageFolder . '/' . $currentImage->image));
                }

                $this->inventoryImageRepo->bulkDelete(
                    collect($data['deleted_images'])
                        ->pluck('id')
                        ->toArray(),
                    'id'
                );
            }

            // update items stock
            $itemLocations = [];
            foreach ($data['item_locations'] as $keyLocation => $itemLocation) {
                $inventoryCode = rand(100,900) . $dividerCode . $inventoryType->slug . $dividerCode . $keyLocation + 1;
                $userId = null;
                if (
                    (isset($itemLocation['user_id'])) &&
                    (!empty($itemLocation['user_id'])) &&
                    ($itemLocation['user_id'] != 'undefined')
                ) {
                    $userId = getIdFromUid($itemLocation['user_id'], new \Modules\Hrd\Models\Employee());
                }

                $payloadItemLocation = [
                    'current_location' => $itemLocation['location'],
                    'inventory_id' => $inventoryId,
                    'inventory_code' => $inventoryCode,
                    'status' => InventoryStatus::InUse->value,
                    'user_id' => $userId,
                ];

                if (empty($itemLocation['id'])) { // create
                    $this->inventoryItemRepo->store($payloadItemLocation);
                } else { // update
                    $this->inventoryItemRepo->update(
                        collect($payloadItemLocation)->only([
                            'inventory_id',
                            'current_location',
                            'user_id'
                        ])->toArray(),
                        '',
                        'id = ' . $itemLocation['id'],
                    );
                }
            }

            // handle image uploading
            if (isset($data['images'])) {
                foreach ($data['images'] as $image) {
                    $imageNames[] = [
                        'image' => uploadImage($image, $this->imageFolder)
                    ];
                }
    
                $inventory->images()->createMany($imageNames);
            }

            DB::commit();

            return generalResponse(
                __('global.successUpdateInventory'),
                false,
            );
        } catch (\Throwable $th) {
            // rollback image
            if (count($imageNames) > 0) {
                foreach ($imageNames as $imageName) {
                    deleteImage(public_path('storage/' . $this->imageFolder . '/' . $imageName['image']));
                }
            }

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
        DB::beginTransaction();
        try {
            $images = [];
            foreach ($ids as $id) {
                $inventory = $this->repo->show(
                    $id,
                    'id,uid',
                    [
                        'items:id,inventory_id,inventory_code,status,current_location',
                        'images:id,image,inventory_id',
                    ]
                );

                // store images and send to job
                if (count($inventory->images) > 0) {
                    foreach ($inventory->images as $image) {
                        $images[] = $image;
                    }
                }

                $this->inventoryItemRepo->delete($inventory->id, 'inventory_id');
                $this->inventoryImageRepo->delete($inventory->id, 'inventory_id');
            }

            $this->repo->bulkDelete($ids, 'uid');

            \Modules\Inventory\Jobs\DeleteImageJob::dispatch($images)->afterCommit();

            DB::commit();

            return generalResponse(
                __('global.successDeleteInventory'),
                false,
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }
}