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
use Modules\Inventory\Repository\SupplierRepository;
use Modules\Inventory\Repository\InventoryRepository;
use Modules\Inventory\Repository\InventoryTypeRepository;
use Modules\Inventory\Repository\InventoryImageRepository;
use Modules\Production\Repository\ProjectEquipmentRepository;
use Modules\Production\Repository\ProjectRepository;
use Modules\Inventory\Repository\CustomInventoryRepository;
use Modules\Inventory\Repository\CustomInventoryDetailRepository;
use Modules\Inventory\Repository\UnitRepository;
use Modules\Company\Repository\SettingRepository;
use Modules\Company\Services\SettingService;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class InventoryService {
    private $repo;

    private $inventoryTypeRepo;

    private $inventoryItemRepo;

    private $inventoryImageRepo;

    private $brandRepo;

    private $supplierRepo;

    private $projectEquipmentRepo;

    private $projectRepo;

    private $customItemRepo;

    private $customItemDetailRepo;

    private $unitRepo;

    private $settingRepo;

    private $settingService;

    private $employeeRepo;

    private string $imageFolder = 'inventory';

    private string $buildSeriesPrefix = 'CB-';

    /**
     * Construction Data
     */
    public function __construct()
    {
        $this->repo = new InventoryRepository;

        $this->unitRepo = new UnitRepository;

        $this->inventoryTypeRepo = new InventoryTypeRepository;

        $this->inventoryItemRepo = new InventoryItemRepository;

        $this->inventoryImageRepo = new InventoryImageRepository;

        $this->projectEquipmentRepo = new ProjectEquipmentRepository;

        $this->brandRepo = new \Modules\Inventory\Repository\BrandRepository();

        $this->supplierRepo = new SupplierRepository;

        $this->employeeRepo = new \Modules\Hrd\Repository\EmployeeRepository();

        $this->projectRepo = new ProjectRepository;

        $this->customItemRepo = new CustomInventoryRepository;

        $this->customItemDetailRepo = new CustomInventoryDetailRepository;

        $this->settingRepo = new SettingRepository;

        $this->settingService = new SettingService;
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
            $data = \Maatwebsite\Excel\Facades\Excel::toArray(new \App\Imports\InventoryImport, $data['excel']);
            
            $output = [];

            $data = $data[0];

            // validate template
            if ($data[0][0] != \App\Enums\ExcelTemplate\Inventory::InventoryTemplate->value) {
                throw new \App\Exceptions\TemplateNotValid();
            }

            unset($data[0]);
            unset($data[1]);

            $data = array_values($data);

            $groupBy = [];
            foreach ($data as $key => $val) {
                $groupBy[$val[0]][] = $val;
            }

            $error = [];
            $payload = [];
            $row = 0;
            foreach ($groupBy as $name => $value) {
                $errorRow = false;

                foreach ($value as $inventory) {
                    if (
                        empty($inventory[0]) || 
                        empty($inventory[1]) ||
                        empty($inventory[2]) ||
                        empty($inventory[3]) ||
                        empty($inventory[4])
                    ) {
                        $errorRow = true;
                        $error[] = __('global.rowInventoryTemplateNotValid', ['row' => $row + 1]);
                    }
                }

                // validate unique item
                if (!$errorRow) {
                    $check = $this->repo->show('dummy', 'id', [], "lower(name) = '" . strtolower($name) . "'");

                    if ($check) {
                        $errorRow = true;
                        $error[] = __('global.itemIsAlreadyExists', ['name' => $name]);
                    }
                }

                // validate user id
                foreach ($value as $userData) {
                    if ($userData[9] == 'User' && empty($userData[10])) {
                        $errorRow = true;
                        $error[] = __('global.inventoryShouldHaveAEmployeeName', ['name' => $name]);
                    }
                }

                if (!$errorRow) {
                    $brand = $this->brandRepo->show('dummy', 'id', [], "lower(name) = '" . strtolower($value[0][4]) . "'");
                    $type = $this->inventoryTypeRepo->show('dummy', 'id,slug', [], "lower(name) = '" . strtolower($value[0][3]) . "'");
                    $supplier = $this->supplierRepo->show('dummy', 'id', [], "lower(name) = '" . strtolower($value[0][5]) . "'");
                    $unit = $this->unitRepo->show('dummy', 'id', [], "lower(name) = 'pcs'");

                    $items = [];
                    foreach ($value as $itemDetail) {
                        if ($itemDetail[9] == 'User') {
                            $employee = $this->employeeRepo->show('dummy', 'id', [], "lower(employee_id) = '" . strtolower($itemDetail[10]) . "'");
                        }

                        $dividerCode = '-';

                        $countItems = 0;

                        $inventoryCode = rand(100,900) . $dividerCode . $type->slug . $dividerCode . $countItems + 1;

                        $items[] = [
                            'current_location' => $itemDetail[9] == 'User' ? 1 : 2,
                            'user_id' => $itemDetail[9] == 'User' ? $employee->id : null,
                            'inventory_id' => '',
                            'inventory_code' => $inventoryCode,
                            'status' => 1,
                        ];
                    }

                    $payload[] = [
                        'name' => $name,
                        'purchase_price' => $value[0][1],
                        // 'brand' => $value[0][4],
                        'brand_id' => $brand->id,
                        'unit_id' => $unit->id,
                        // 'item_type_raw' => $value[0][3],
                        'item_type' => $type->id,
                        'warehouse_id' => $value[0][2] == 'Office' ? 1 : 2,
                        'year_of_purchase' => $value[0][7],
                        'warranty' => $value[0][8],
                        // 'supplier_raw' => $value[0][5],
                        'supplier_id' => $supplier->id,
                        'items' => $items,
                    ];
                }

                $row++;
            }

            foreach ($payload as $item) {
                $inventory = $this->repo->store(collect($item)->except(['items'])->toArray());

                $inventory->items()->createMany($item['items']);
            }

            DB::commit();
    
            return generalResponse(
                __("global.importInventorySuccess"),
                false,
                [
                    'error' => $error,
                ]
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    public function createBrandTemplate()
    {
        $excel = new \App\Services\ExcelService();

        $excel->setValue('A1', 'TEMPLATE BRAND');
        $excel->mergeCells('A1:F1');
        $excel->alignCenter('A1:F1');
        $excel->setAsBold('A1');

        $excel->setValue('A5', "Nama");
        $excel->setAsBold('A5');

        $excel->save('static_file/template_brand.xlsx');

        return \Illuminate\Support\Facades\Storage::disk('public_path')->download('static_file/template_brand.xlsx');
    }

    public function createSupplierTemplate()
    {
        $excel = new \App\Services\ExcelService();

        $excel->setValue('A1', 'TEMPLATE SUPPLIER');
        $excel->mergeCells('A1:F1');
        $excel->alignCenter('A1:F1');
        $excel->setAsBold('A1');

        $excel->setValue('A5', "Nama");
        $excel->setAsBold('A5');

        $excel->save('static_file/template_supplier.xlsx');

        return \Illuminate\Support\Facades\Storage::disk('public_path')->download('static_file/template_supplier.xlsx');
    }

    public function createUnitTemplate()
    {
        $excel = new \App\Services\ExcelService();

        $excel->setValue('A1', 'TEMPLATE UNIT');
        $excel->mergeCells('A1:F1');
        $excel->alignCenter('A1:F1');
        $excel->setAsBold('A1');

        $excel->setValue('A5', "Nama");
        $excel->setAsBold('A5');

        $excel->save('static_file/template_unit.xlsx');

        return \Illuminate\Support\Facades\Storage::disk('public_path')->download('static_file/template_unit.xlsx');
    }

    public function createInventoryTypeTemplate()
    {
        $excel = new \App\Services\ExcelService();

        $excel->setValue('A1', 'TEMPLATE INVENTORY TYPE');
        $excel->mergeCells('A1:F1');
        $excel->alignCenter('A1:F1');
        $excel->setAsBold('A1');

        $excel->setValue('A5', "Nama");
        $excel->setAsBold('A5');

        $excel->save('static_file/template_inventory_type.xlsx');

        return \Illuminate\Support\Facades\Storage::disk('public_path')->download('static_file/template_inventory_type.xlsx');
    }

    /**
     * Generate inventory excel template
     */
    public function createExcelTemplate()
    {
        // delete current file
        if (file_exists(public_path('static_file/template_inventory.xlsx'))) {
            unlink(public_path('static_file/template_inventory.xlsx'));
        }

        sleep(1);

        $excel = new \App\Services\ExcelService();

        $excel->createSheet('Template', 0);
        $excel->setActiveSheet('Template');

        $excel->setValue('A1', 'TEMPLATE INVENTORY LIST');
        $excel->mergeCells('A1:F1');
        $excel->alignCenter('A1:F1');
        
        $excel->setAsBold('A1');
        $excel->setValue('A4', "Nama Barang");
        $excel->setValue('B4', "Harga Barang");
        $excel->setValue('C4', "Lokasi Gudang");
        $excel->setValue('D4', 'Pilih Tipe');
        $excel->setValue('E4', 'Pilih Merek');
        $excel->setValue('F4', 'Pilih Supplier');
        $excel->setValue('G4', 'Model');
        $excel->setValue('H4', 'Tahun Pembelian');
        $excel->setValue('I4', 'Garansi');
        $excel->setValue('J4', 'Lokasi Unit');
        $excel->setValue('K4', 'Nama karyawan pemegang unit (Jika diperlukan saja)');
        
        $excel->autoSize(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K']);

        $excel->setAsBold('A4');
        $excel->setAsBold('B4');
        $excel->setAsBold('C4');
        $excel->setAsBold('D4');
        $excel->setAsBold('E4');
        $excel->setAsBold('F4');
        $excel->setAsBold('G4');
        $excel->setAsBold('H4');
        $excel->setAsBold('I4');
        $excel->setAsBold('J4');
        $excel->setAsBold('K4');

        $typeListRaw = $this->inventoryTypeRepo->list('id,name');
        $typeList = implode(',', collect($typeListRaw)->pluck('name')->toArray());

        logging('typelist', [$typeList]);

        $brandsRaw = $this->brandRepo->list('id,name');
        $brands = implode(',', collect($brandsRaw)->pluck('name')->toArray());

        $suppliersRaw = $this->supplierRepo->list('id,name');
        $suppliers = implode(',', collect($suppliersRaw)->pluck('name')->toArray());

        $warehouseData = \App\Enums\Inventory\Warehouse::cases();
        $warehouseList = [];
        foreach ($warehouseData as $warehouse) {
            $warehouseList[] = $warehouse->label();
        }
        $warehouseList = implode(',', $warehouseList);

        $locations = "User, Warehouse";

        $bulkSheet = 100;

        for ($a = 5; $a < $bulkSheet; $a++) {
            $excel->setAsTypeList($warehouseList, "C{$a}", 'STOP! Ada Error', 'Pilih gudang kawan', 'Pilih Gudang');
            
            if ($typeListRaw->count() > 0) {
                $excel->setAsTypeList($typeList, "D{$a}", 'STOP! Ada Error', 'Pilih tipe dulu kawan', 'Pilih Tipe');
            }

            if ($brandsRaw->count() > 0) {
                $excel->setAsTypeList($brands, "E{$a}", 'STOP! Ada Error', 'Pilih brand nya kawan', 'Pilih Brand');
            }

            if ($suppliersRaw->count() > 0) {
                $excel->setAsTypeList($suppliers, "F{$a}", 'STOP! Ada Error', 'Pilih brand nya kawan', 'Pilih Brand');
            }
            
            $excel->setAsTypeList($locations, "J{$a}", 'STOP! Ada Error', 'Pilih lokasi nya kawan', 'Pilih Lokasi');
        }

        $employees = $this->employeeRepo->list('id,name,employee_id', 'status != ' . \App\Enums\Employee\Status::Inactive->value);

        $excel->createSheet('Employee List', 1);
        $excel->setActiveSheet('Employee List');

        $excel->setValue('A1', 'Employee List');
        $excel->setAsBold('A1');
        $excel->mergeCells('A1:B1');
        $excel->alignCenter('A1:B1');

        $excel->setValue('A4', 'ID');
        $excel->setValue('B4', 'Name');
        $excel->setAsBold('B4');
        $excel->setAsBold('A4');

        $startCell = '5';
        foreach($employees->toArray() as $employee) {
            $excel->setValue("A{$startCell}", $employee['employee_id']);
            $excel->setValue("B{$startCell}", $employee['name']);

            $startCell++;
        }
        $excel->autoSize(['A', 'B']);

        $excel->setActiveSheet('Template');

        $excel->save('static_file/template_inventory.xlsx');

        logging('fil exists', [\Illuminate\Support\Facades\Storage::disk('public_path')->exists('static_file/template_inventory.xlsx')]);

        return \Illuminate\Support\Facades\Storage::disk('public_path')->download('static_file/template_inventory.xlsx');
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

            $paginated = collect((object) $paginated)->map(function ($item) {
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

            $paginated = collect((object) $paginated)->map(function ($item) use ($inventoryStatuses) {
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

        $data = collect((object) $data)->map(function ($item) {
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

            $paginated = collect((object) $paginated)->map(function ($item) use ($locations) {
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

            $data = collect((object) $data)->map(function ($item) {
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