<?php

namespace Modules\Inventory\Services;

use App\Enums\ErrorCode\Code;
use App\Enums\Inventory\InventoryStatus;
use App\Enums\Production\RequestEquipmentStatus;
use App\Exports\SummaryInventoryReport;
use App\Services\GeneralService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Company\Repository\SettingRepository;
use Modules\Company\Services\SettingService;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Inventory\Jobs\InventoryExportHasBeenCompleted;
use Modules\Inventory\Models\Brand;
use Modules\Inventory\Models\Inventory;
use Modules\Inventory\Models\InventoryType;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Repository\BrandRepository;
use Modules\Inventory\Repository\CustomInventoryDetailRepository;
use Modules\Inventory\Repository\CustomInventoryRepository;
use Modules\Inventory\Repository\InventoryImageRepository;
use Modules\Inventory\Repository\InventoryItemRepository;
use Modules\Inventory\Repository\InventoryRepository;
use Modules\Inventory\Repository\InventoryTypeRepository;
use Modules\Inventory\Repository\SupplierRepository;
use Modules\Inventory\Repository\UnitRepository;
use Modules\Production\Repository\ProjectEquipmentRepository;
use Modules\Production\Repository\ProjectRepository;

class InventoryService
{
    private InventoryRepository $repo;

    private InventoryTypeRepository $inventoryTypeRepo;

    private InventoryItemRepository $inventoryItemRepo;

    private InventoryImageRepository $inventoryImageRepo;

    private BrandRepository $brandRepo;

    private SupplierRepository $supplierRepo;

    private ProjectEquipmentRepository $projectEquipmentRepo;

    private ProjectRepository $projectRepo;

    private CustomInventoryRepository $customItemRepo;

    private CustomInventoryDetailRepository $customItemDetailRepo;

    private UnitRepository $unitRepo;

    private SettingRepository $settingRepo;

    private SettingService $settingService;

    private EmployeeRepository $employeeRepo;

    private string $imageFolder = 'inventory';

    private string $buildSeriesPrefix = 'CB-';

    private GeneralService $generalService;

    /**
     * Construction Data
     */
    public function __construct(
        InventoryRepository $repo,
        UnitRepository $unitRepo,
        InventoryTypeRepository $inventoryTypeRepo,
        InventoryImageRepository $inventoryImageRepo,
        InventoryItemRepository $inventoryItemRepo,
        ProjectEquipmentRepository $projectEquipmentRepo,
        BrandRepository $brandRepo,
        SupplierRepository $supplierRepo,
        EmployeeRepository $employeeRepo,
        ProjectRepository $projectRepo,
        CustomInventoryRepository $customInventoryRepo,
        CustomInventoryDetailRepository $customInventoryDetailRepo,
        SettingRepository $settingRepo,
        SettingService $settingService,
        GeneralService $generalService
    ) {
        $this->repo = $repo;

        $this->unitRepo = $unitRepo;

        $this->inventoryTypeRepo = $inventoryTypeRepo;

        $this->inventoryItemRepo = $inventoryItemRepo;

        $this->inventoryImageRepo = $inventoryImageRepo;

        $this->projectEquipmentRepo = $projectEquipmentRepo;

        $this->brandRepo = $brandRepo;

        $this->supplierRepo = $supplierRepo;

        $this->employeeRepo = $employeeRepo;

        $this->projectRepo = $projectRepo;

        $this->customItemRepo = $customInventoryRepo;

        $this->customItemDetailRepo = $customInventoryDetailRepo;

        $this->settingRepo = $settingRepo;

        $this->settingService = $settingService;

        $this->generalService = $generalService;
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
            $data = \Maatwebsite\Excel\Facades\Excel::toArray(new \App\Imports\InventoryImport, $data['excel']);

            $output = [];

            $data = $data[0];

            // validate template
            if ($data[0][0] != \App\Enums\ExcelTemplate\Inventory::InventoryTemplate->value) {
                throw new \App\Exceptions\TemplateNotValid;
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
                        ($inventory[1] < 0) ||
                        empty($inventory[2]) ||
                        empty($inventory[3]) ||
                        empty($inventory[4])
                    ) {
                        $errorRow = true;
                        $error[] = __('global.rowInventoryTemplateNotValid', ['row' => $row + 1]);
                    }
                }

                // validate unique item
                if (! $errorRow) {
                    $check = $this->repo->show('dummy', 'id', [], "lower(name) = '".strtolower($name)."'");

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

                if (! $errorRow) {
                    $brand = $this->brandRepo->show('dummy', 'id', [], "lower(name) = '".strtolower($value[0][4])."'");
                    $type = $this->inventoryTypeRepo->show('dummy', 'id,slug', [], "lower(name) = '".strtolower($value[0][3])."'");
                    $supplier = $this->supplierRepo->show('dummy', 'id', [], "lower(name) = '".strtolower($value[0][5])."'");
                    $unit = $this->unitRepo->show('dummy', 'id', [], "lower(name) = 'pcs'");

                    $items = [];
                    foreach ($value as $itemDetail) {
                        if ($itemDetail[9] == 'User') {
                            $employee = $this->employeeRepo->show('dummy', 'id', [], "lower(employee_id) = '".strtolower($itemDetail[10])."'");
                        }

                        $dividerCode = '-';

                        $countItems = 0;

                        $inventoryCode = rand(100, 900).$dividerCode.$type->slug.$dividerCode.$countItems + 1;

                        $qrcode = generateQrcode($inventoryCode, 'inventory/qrcode/qr'.rand(100, 900).date('Yhs').'.png');

                        $items[] = [
                            'current_location' => $itemDetail[9] == 'User' ? 1 : 2,
                            'user_id' => $itemDetail[9] == 'User' ? $employee->id : null,
                            'inventory_id' => '',
                            'inventory_code' => $inventoryCode,
                            'status' => 1,
                            'qrcode' => $qrcode,
                        ];
                    }

                    $price = str_replace(',', '', $value[0][1]);
                    $price = str_replace('.', '', $price);

                    $payload[] = [
                        'name' => $name,
                        'purchase_price' => $price,
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
                        'stock' => count($items),
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
                __('global.importInventorySuccess'),
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
        $excel = new \App\Services\ExcelService;

        $excel->setValue('A1', 'TEMPLATE BRAND');
        $excel->mergeCells('A1:F1');
        $excel->alignCenter('A1:F1');
        $excel->setAsBold('A1');

        $excel->setValue('A5', 'Nama');
        $excel->setAsBold('A5');

        $excel->save(storage_path('app/public/static-file/template_brand.xlsx'));

        return \Illuminate\Support\Facades\Storage::download('static-file/template_brand.xlsx');
    }

    public function createSupplierTemplate()
    {
        $excel = new \App\Services\ExcelService;

        $excel->setValue('A1', 'TEMPLATE SUPPLIER');
        $excel->mergeCells('A1:F1');
        $excel->alignCenter('A1:F1');
        $excel->setAsBold('A1');

        $excel->setValue('A5', 'Nama');
        $excel->setAsBold('A5');

        $excel->save(storage_path('app/public/static-file/template_supplier.xlsx'));

        return \Illuminate\Support\Facades\Storage::download('static-file/template_supplier.xlsx');
    }

    public function createUnitTemplate()
    {
        $excel = new \App\Services\ExcelService;

        $excel->setValue('A1', 'TEMPLATE UNIT');
        $excel->mergeCells('A1:F1');
        $excel->alignCenter('A1:F1');
        $excel->setAsBold('A1');

        $excel->setValue('A5', 'Nama');
        $excel->setAsBold('A5');

        $excel->save(storage_path('app/public/static-file/template_unit.xlsx'));

        return \Illuminate\Support\Facades\Storage::download('static-file/template_unit.xlsx');
    }

    public function createInventoryTypeTemplate()
    {
        $excel = new \App\Services\ExcelService;

        $excel->setValue('A1', 'TEMPLATE INVENTORY TYPE');
        $excel->mergeCells('A1:F1');
        $excel->alignCenter('A1:F1');
        $excel->setAsBold('A1');

        $excel->setValue('A5', 'Nama');
        $excel->setAsBold('A5');

        $excel->save(storage_path('app/public/static-file/template_inventory_type.xlsx'));

        return \Illuminate\Support\Facades\Storage::download('static-file/template_inventory_type.xlsx');
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

        $excel = new \App\Services\ExcelService;

        $excel->createSheet('Template', 0);
        $excel->setActiveSheet('Template');

        $excel->setValue('A1', 'TEMPLATE INVENTORY LIST');
        $excel->mergeCells('A1:F1');
        $excel->alignCenter('A1:F1');

        $excel->setAsBold('A1');
        $excel->setValue('A4', 'Nama Barang');
        $excel->setValue('B4', 'Harga Barang');
        $excel->setValue('C4', 'Lokasi Gudang');
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

        $locations = 'User, Warehouse';

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

        $employees = $this->employeeRepo->list('id,name,employee_id', 'status != '.\App\Enums\Employee\Status::Inactive->value);

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
        foreach ($employees->toArray() as $employee) {
            $excel->setValue("A{$startCell}", $employee['employee_id']);
            $excel->setValue("B{$startCell}", $employee['name']);

            $startCell++;
        }
        $excel->autoSize(['A', 'B']);

        $excel->setActiveSheet('Template');

        $excel->save(storage_path('app/public/static-file/template_inventory.xlsx'));

        return \Illuminate\Support\Facades\Storage::download('static-file/template_inventory.xlsx');
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
                    'query' => 'status > 0',
                ],
            ];

            if (! empty($search)) { // array
                if (! empty($search['name']) && empty($where)) {
                    $name = strtolower($search['name']);
                    $where = "LOWER(name) LIKE '%{$name}%'";
                } elseif (! empty($search['name']) && ! empty($where)) {
                    $name = strtolower($search['name']);
                    $where .= " AND LOWER(name) LIKE '%{$name}%'";
                }
            }

            $totalData = $this->projectRepo->list('id', $where, $relation, $whereHas)->count();

            if ($itemsPerPage < 0) {
                $itemsPerPage = $this->projectRepo->list('id', $where, [], $whereHas)->count();
            }

            $paginated = $this->projectRepo->pagination(
                $select,
                $where,
                $relation,
                $itemsPerPage,
                $page,
                $whereHas
            );

            $paginated = collect((object) $paginated)->map(function ($item) {
                $equipmentStatuses = collect($item->equipments)->pluck('status')->toArray();

                $statusText = __('global.requested');
                $statusColor = 'primary';
                $unique = array_values(array_unique($equipmentStatuses));
                if (count($unique) == 1 && $unique[0] == \App\Enums\Production\RequestEquipmentStatus::Ready->value) {
                    $statusText = __('global.equipmentReady');
                    $statusColor = 'success';
                } elseif (count($unique) == 1 && $unique[0] == \App\Enums\Production\RequestEquipmentStatus::Return->value) {
                    $statusText = __('global.needToCheckAfterReturn');
                    $statusColor = 'red';
                } elseif (count($unique) == 1 && $unique[0] == \App\Enums\Production\RequestEquipmentStatus::Cancel->value) {
                    $statusText = __('global.canceled');
                    $statusColor = 'orange-darken-3';
                } elseif (count($unique) == 1 && $unique[0] == \App\Enums\Production\RequestEquipmentStatus::CompleteAndNotReturn->value) {
                    $statusText = __('global.completeAndNotYetReturned');
                    $statusColor = 'lime-darken-2';
                }
                if (in_array(\App\Enums\Production\RequestEquipmentStatus::OnEvent->value, $equipmentStatuses)) {
                    $statusText = __('global.onEvent');
                    $statusColor = 'info';
                }

                return [
                    'uid' => $item->uid,
                    'project_date' => date('d F Y', strtotime($item->project_date)),
                    'name' => $item->name,
                    'status' => $statusText,
                    'status_color' => $statusColor,
                    'equipment_total' => count($item->equipments),
                ];
            })->all();

            return generalResponse(
                'Success',
                false,
                [
                    'paginated' => $paginated,
                    'totalData' => $totalData,
                ]
            );
        } catch (\Throwable $th) {
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
            $itemsPerPage = request('itemsPerPage') ?? config('app.pagination_length');
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $search = request('search');
            $whereHas = [];

            if (! empty($search)) { // array
                $where = formatSearchConditions($search['filters'], $where);
            }

            $sort = 'name asc';
            if (request('sort')) {
                $sort = '';
                foreach (request('sort') as $sortList) {
                    if ($sortList['field'] == 'name') {
                        $sort = $sortList['field']." {$sortList['order']},";
                    } else {
                        $sort .= ','.$sortList['field']." {$sortList['order']},";
                    }
                }

                $sort = rtrim($sort, ',');
                $sort = ltrim($sort, ',');
            }

            $paginated = $this->repo->pagination(
                $select,
                $where,
                $relation,
                $itemsPerPage,
                $page,
                $whereHas,
                $sort
            );

            $inventoryStatuses = InventoryStatus::cases();

            $paginated = collect((object) $paginated)->map(function ($item) {
                $item['stock'] = count($item->items);
                $unit = $item->unit ? $item->unit->name : '';

                $locationGroup = collect($item->items)->groupBy('current_location');
                $location = [];
                foreach ($locationGroup as $locationId => $loc) {
                    $location[] = [
                        'text' => count($locationGroup[$locationId]).' '.$locationGroup[$locationId][0]['location'],
                        'color' => $locationGroup[$locationId][0]['location_badge'],

                    ];

                }

                return [
                    'uid' => $item->uid,
                    'name' => $item->name,
                    'stock' => count($item->items).' '.$unit,
                    'brand' => $item->brand ? $item->brand->name : '-',
                    'image' => $item->image ? asset("storage/{$this->imageFolder}/{$item->image->image}") : asset('images/noimage.png'),
                    'year_of_purchase' => $item->year_of_purchase ?? '-',
                    'purchase_price' => config('company.currency').' '.number_format(collect($item->items)->pluck('purchase_price')->sum(), 0, config('company.pricing_divider'), config('company.pricing_divider')),
                    'items' => collect($item->items)->map(function ($inventoryItem) {
                        return [
                            'id' => $inventoryItem->id,
                            'inventory_code' => $inventoryItem->inventory_code,
                            'status' => $inventoryItem->status_text,
                            'purchase_price' => $inventoryItem->purchase_price ? number_format($inventoryItem->purchase_price) : '0',
                            'warranty' => $inventoryItem->warranty ?? '-',
                            'year_of_purchase' => $inventoryItem->year_of_purchase ?? '-',
                            'qrcode' => $inventoryItem->qrcode ? asset("storage/{$inventoryItem->qrcode}") : asset('images/noimage.png'),
                        ];
                    }),
                    'locations' => $location,
                    'warranty' => $item->warranty ? $item->warranty.' '.__('global.year') : '-',
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

        if (! empty(request('search'))) {
            $search = strtolower(request('search'));
            $where = "lower(name) like '%{$search}%'";
        }

        // get all available items
        $currentCustomData = $this->customItemRepo->list('id', '', ['items:id,custom_inventory_id,inventory_id']);
        $inventoryItemIds = [];
        foreach ($currentCustomData as $current) {
            foreach ($current->items as $currentItem) {
                $inventoryItemIds[] = $currentItem->inventory_id;
            }
        }

        $inventoryItemIds = array_values(array_unique($inventoryItemIds));

        $data = $this->repo->list(
            'id,uid,name,stock,unit_id',
            $where,
            [
                'unit:id,name',
                'items' => function ($q) use ($inventoryItemIds) {
                    $q->selectRaw('id,inventory_id,purchase_price,inventory_code')
                        ->whereNotIn('id', $inventoryItemIds);
                },
            ],
            'warehouse_id DESC'
        );

        $output = [];
        foreach ($data as $inventory) {
            foreach ($inventory->items as $item) {
                if ($inventory->stock > 0) {
                    $output[] = [
                        'name' => $inventory->name,
                        'uid' => $inventory->uid,
                        'display_image' => $inventory->display_image,
                        'price' => $item->purchase_price,
                        'series' => $item->inventory_code,
                        'id' => $item->id,
                        'stock' => $inventory->stock,
                    ];
                }
            }
        }

        return generalResponse(
            'success',
            false,
            $output,
        );
    }

    public function getBundleInventories()
    {
        $data = $this->customItemRepo->list(
            'id,type,name,uid',
            "type = 'itemvj'",
            [
                'items:id,inventory_id,custom_inventory_id,qty',
                'items.inventory:id,inventory_id',
                'items.inventory.inventory:id,name',
            ]
        );

        $output = [];
        foreach ($data as $item) {

            $items = [];
            foreach ($item->items as $inventory) {
                for ($a = 0; $a < $inventory->qty; $a++) {
                    $items[] = [
                        'id' => $inventory->inventory->uid,
                        'name' => $inventory->inventory->name,
                        'qty' => 1,
                    ];
                }
            }

            $output[] = [
                'title' => $item->name,
                'value' => $item->uid,
                'location' => '',
                'items' => $items,
            ];
        }

        return generalResponse(
            'success',
            false,
            $output
        );
    }

    public function getAllInventoryItems()
    {
        $data = $this->inventoryItemRepo->list('id,inventory_id,status', '', ['inventory:id,name']);

        return generalResponse(
            'success',
            false,
            collect((object) $data)->map(function ($item) {
                return [
                    'value' => $item->id,
                    'title' => $item->inventory->name,
                ];
            })->toArray()
        );
    }

    /**
     * Get equipment list for project request
     */
    public function getEquipmentForProjectRequest(): array
    {
        try {
            $type = request('type');

            if ($type == 'inventory_item') {
                $inventories = $this->repo->list(
                    select: 'id,name,stock,item_type,brand_id',
                    where: 'warehouse_id = 2 AND stock > 0'
                );
            } else {
                $inventories = $this->customItemRepo->list(
                    select: 'id,build_series,name,type,location,default_request_item,barcode'
                );
            }

            return generalResponse(
                message: 'Success',
                data: $inventories->toArray()
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function getAll()
    {
        $where = '';
        if (
            (request('type')) &&
            (request('type') == 'bundle')
        ) {
            return $this->getBundleInventories();
        }

        if (
            (request('type')) &&
            (request('type') == 'inventory_item')
        ) {
            return $this->getAllInventoryItems();
        }

        $data = $this->repo->list('id,uid as value,name as title', '', ['items', 'image']);

        $data = collect((object) $data)->map(function ($item) {
            $locationGroup = collect($item->items)->groupBy('current_location');
            $location = [];
            foreach ($locationGroup as $locationId => $loc) {
                $location[] = [
                    'text' => count($locationGroup[$locationId]).' '.$locationGroup[$locationId][0]['location'],
                    'color' => $locationGroup[$locationId][0]['location_badge'],
                ];

            }

            $image = $item->image ? asset('storage/inventory/'.$item->image->image) : asset('images/noimage.png');

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
                    'items:id,inventory_id,inventory_code,status,current_location,user_id,qrcode,purchase_price,warranty,year_of_purchase',
                    'items.employee:id,uid,name',
                    'items.employee:id,uid',
                    'images:id,image,inventory_id',
                    'itemTypeRelation:id,uid,name',
                    'projectEquipments:id,inventory_id,status',
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

            // check relation to project equipments (project request equipment)
            // if this equipment have relation and have status Ready or requested, then item cannot be delete
            $projectRequest = collect($data->projectEquipments)->pluck('status')->toArray();
            $cannotBeDelete = false;
            if (count($projectRequest) > 0) {
                if (
                    in_array(RequestEquipmentStatus::Requested->value, $projectRequest) ||
                    in_array(RequestEquipmentStatus::Ready->value, $projectRequest)
                ) {
                    $cannotBeDelete = true;
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
                'purchase_price' => config('company.currency').' '.number_format(collect($data->items)->pluck('purchase_price')->sum(), 0, config('company.pricing_divider'), config('company.pricing_divider')),
                'price_raw' => $data->purchase_price ? $data->purchase_price : '',
                'last_update' => date('d F Y H:i', strtotime($data->updated_at)),
                'warehouse_id' => $data->warehouse_id,
                'warehouse_text' => $warehouseText ?? '',
                'warehouse_color' => $warehouseColor ?? '',
                'warranty' => $data->warranty,
                'deleteable' => $cannotBeDelete,
                'items' => collect($data->items)->map(function ($item) {
                    return [
                        'inventory_code' => $item->inventory_code,
                        'status' => $item->status,
                        'status_text' => $item->status_text,
                        'current_location' => $item->current_location,
                        'purchase_price' => config('company.currency').' '.number_format($item->purchase_price, 0, config('company.pricing_divider'), config('company.pricing_divider')),
                        'purchase_price_raw' => $item->purchase_price,
                        'warranty' => $item->warranty,
                        'year_of_purchase' => $item->year_of_purchase,
                        'location' => $item->location,
                        'id' => $item->id,
                        'user_id' => $item->employee ? $item->employee->uid : null,
                        'user' => $item->employee ? $item->employee->name : null,
                        'qrcode' => asset('storage/'.$item->qrcode),
                    ];
                }),
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
                ], '', 'default_request_item = 1');
            }

            $this->customItemRepo->update(
                collect($data)->except(['inventories', 'removed_items'])->toArray(),
                $uid
            );

            $defaultItem = $this->customItemRepo->list('id', 'default_request_item = 1')->count();
            $this->settingService->storeVariables([
                'have_default_request_item' => $defaultItem > 0 ? 1 : 0,
            ]);

            $itemData = $this->customItemRepo->show($uid);

            foreach ($data['inventories'] as $inventory) {
                if (isset($inventory['current_id'])) {
                    $this->customItemDetailRepo->update([
                        'qty' => 1,
                        'price' => $inventory['price'],
                    ], '', 'id = '.$inventory['current_id']);
                } else {
                    $itemData->items()->create([
                        'inventory_id' => $inventory['id'],
                        'qty' => 1,
                        'price' => $inventory['price'],
                    ]);
                }
            }

            foreach ($data['removed_items'] as $removedItems) {
                $this->customItemDetailRepo->delete($removedItems['current_id']);
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

            $data['build_series'] = $this->buildSeriesPrefix.generateSequenceNumber($lengthData + 1);

            $data['location'] = \App\Enums\Inventory\Location::InWarehouse->value;

            // override default request item if needed
            if ($data['default_request_item']) {
                $this->customItemRepo->update([
                    'default_request_item' => 0,
                ], '', 'default_request_item = 1');

                $this->settingService->storeVariables([
                    'have_default_request_item' => 1,
                ]);
            }

            $item = $this->customItemRepo->store(
                collect($data)->except(['inventories'])->toArray()
            );

            $inventories = $data['inventories'];

            $item->items()->createMany(
                collect($inventories)->map(function ($item) {
                    return [
                        'inventory_id' => $item['id'],
                        'qty' => 1,
                        'price' => $item['price'],
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

            if (! empty($search)) { // array
                $where = formatSearchConditions($search['filters'], $where);
            }

            $sort = 'name asc';
            if (request('sort')) {
                $sort = '';
                foreach (request('sort') as $sortList) {
                    if ($sortList['field'] == 'name') {
                        $sort = $sortList['field']." {$sortList['order']},";
                    } else {
                        $sort .= ','.$sortList['field']." {$sortList['order']},";
                    }
                }

                $sort = rtrim($sort, ',');
                $sort = ltrim($sort, ',');
            }

            if ($itemsPerPage < 0) {
                $itemsPerPage = $this->customItemRepo->list('id', $where)->count();
            }

            $paginated = $this->customItemRepo->pagination(
                '*',
                $where,
                ['items.inventory:id,inventory_id', 'items.inventory.inventory:id,uid,name'],
                $itemsPerPage,
                $page,
                $whereHas,
                $sort
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
                    'type' => $item->type == 'itemvj' ? 'Item VJ' : 'PC Rakitan',
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
        $data = $this->customItemRepo->show($uid, '*', ['items.inventory:id,inventory_id,inventory_code,purchase_price', 'items.inventory.inventory:id,name,stock,uid']);
        $items = collect($data->items)->map(function ($item) {
            return [
                'display_image' => $item->inventory->inventory->display_image,
                'id' => $item->inventory->id,
                'name' => $item->inventory->inventory->name,
                'price' => $item->inventory->purchase_price,
                'series' => $item->inventory->inventory_code,
                'stock' => $item->inventory->inventory->stock,
                'uid' => $item->inventory->inventory->uid,
                'current_id' => $item->id,
            ];
        });

        return generalResponse(
            'success',
            false,
            [
                'raw' => $data,
                'items' => $items,
            ],
        );
    }

    /**
     * Store data
     */
    public function store(array $data): array
    {
        $imageNames = [];

        DB::beginTransaction();
        try {
            if ((isset($data['supplier_id'])) && (! empty($data['supplier_id']))) {
                $data['supplier_id'] = getIdFromUid($data['supplier_id'], new Supplier);
            }
            $data['brand_id'] = getIdFromUid($data['brand_id'], new Brand);
            $data['unit_id'] = getIdFromUid($data['unit_id'], new Unit);

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
                'warehouse_id',
            ])->toArray());

            // add items
            $this->addItems($inventory, $data);

            // handle image uploading
            if (isset($data['images'])) {
                foreach ($data['images'] as $image) {
                    $imageNames[] = [
                        'image' => uploadBase64($image, $this->imageFolder),
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
                    deleteImage(public_path('storage/'.$this->imageFolder.'/'.$imageName));
                }
            }

            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Add more stock to each product parent
     *
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
                    'items:id,inventory_id',
                ]
            );

            $this->addItems($inventory, $data);

            DB::commit();

            return generalResponse(
                __('global.successAddStock'),
                false
            );
        } catch (\Throwable $th) {
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
        try {
            // create inventory code
            // random int - SLUG item type - Inventory position - order number
            $dividerCode = '-';

            $inventoryType = $inventory->itemTypeRelation;

            $itemLoactions = [];
            $countItems = $inventory->items->count();
            foreach ($data['item_locations'] as $keyLocation => $itemLocation) {
                $inventoryCode = rand(100, 900).$dividerCode.$inventoryType->slug.$dividerCode.$countItems + 1;
                $userId = null;
                if (
                    (isset($itemLocation['user_id'])) &&
                    (! empty($itemLocation['user_id'])) &&
                    ($itemLocation['user_id'] != 'undefined')
                ) {
                    $userId = getIdFromUid($itemLocation['user_id'], new \Modules\Hrd\Models\Employee);
                }

                $qrcode = generateQrcode($inventoryCode, 'inventory/qrcode/qr'.rand(100, 900).date('Yhs').'.png');

                $itemLoactions[] = [
                    'inventory_code' => $inventoryCode,
                    'status' => InventoryStatus::InUse->value,
                    'current_location' => $itemLocation['location'],
                    'user_id' => $userId,
                    'qrcode' => $qrcode,
                    'purchase_price' => $itemLocation['purchase_price'],
                    'warranty' => $itemLocation['warranty'],
                    'year_of_purchase' => $itemLocation['year_of_purchase'],
                ];

                $countItems++;
            }

            // store items
            $inventory->items()->createMany($itemLoactions);
        } catch (\Throwable $th) {
            Log::error($th);
        }
    }

    public function itemList(string $uid)
    {
        try {
            $inventoryId = getIdFromUid($uid, new \Modules\Inventory\Models\Inventory);

            $data = $this->inventoryItemRepo->list(
                'id,inventory_id,inventory_code,status,current_location,user_id,purchase_price,warranty,year_of_purchase',
                'inventory_id = '.$inventoryId,
                [
                    'employee:id,uid,name',
                ]
            );

            $data = collect((object) $data)->map(function ($item) {
                return [
                    'inventory_code' => $item->inventory_code,
                    'status' => $item->status,
                    'status_text' => $item->status_text,
                    'current_location' => $item->current_location,
                    'purchase_price' => number_format($item->purchase_price),
                    'warranty' => $item->warranty,
                    'year_of_purchase' => $item->year_of_purchase,
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
     */
    public function update(
        array $data,
        string $id,
        string $where = ''
    ): array {
        $imageNames = [];

        DB::beginTransaction();
        try {
            if ((isset($data['supplier_id'])) && (! empty($data['supplier_id']))) {
                $data['supplier_id'] = getIdFromUid($data['supplier_id'], new Supplier);
            }
            $data['brand_id'] = getIdFromUid($data['brand_id'], new Brand);
            $data['unit_id'] = getIdFromUid($data['unit_id'], new Unit);
            $inventoryId = getIdFromUid($id, new Inventory);

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
                'stock',
            ])->toArray(), $id);

            $inventory = $this->repo->show($id, 'id,uid,name', ['images']);

            // delete item if exists
            if (! empty($data['deleted_item_stock'])) {
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
            if (! empty($data['deleted_images'])) {
                // delete in folder
                $imageIds = collect($data['deleted_images'])
                    ->pluck('id')
                    ->toArray();
                $whereImage = 'id IN ('.implode(',', $imageIds).')';
                $currentImages = $this->inventoryImageRepo->list('id,image', $whereImage);

                foreach ($currentImages as $currentImage) {
                    deleteImage(public_path('storage/'.$this->imageFolder.'/'.$currentImage->image));
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
                $inventoryCode = rand(100, 900).$dividerCode.$inventoryType->slug.$dividerCode.$keyLocation + 1;
                $userId = null;
                if (
                    (isset($itemLocation['user_id'])) &&
                    (! empty($itemLocation['user_id'])) &&
                    ($itemLocation['user_id'] != 'undefined')
                ) {
                    $userId = getIdFromUid($itemLocation['user_id'], new \Modules\Hrd\Models\Employee);
                }

                $qrcode = generateQrcode($inventoryCode, 'inventory/qrcode/qr'.rand(100, 999).date('Yhs').'.png');

                $payloadItemLocation = [
                    'current_location' => $itemLocation['location'],
                    'inventory_id' => $inventoryId,
                    'inventory_code' => $inventoryCode,
                    'status' => InventoryStatus::InUse->value,
                    'user_id' => $userId,
                    'qrcode' => $qrcode,
                    'purchase_price' => $itemLocation['purchase_price'],
                    'warranty' => $itemLocation['warranty'] == 'null' ? null : $itemLocation['warranty'],
                    'year_of_purchase' => $itemLocation['year_of_purchase'] == 'null' ? null : $itemLocation['year_of_purchase'],
                ];

                if (empty($itemLocation['id'])) { // create
                    $this->inventoryItemRepo->store($payloadItemLocation);
                } else { // update
                    $this->inventoryItemRepo->update(
                        collect($payloadItemLocation)->only([
                            'inventory_id',
                            'current_location',
                            'user_id',
                            'purchase_price',
                            'warranty',
                            'year_of_purchase',
                        ])->toArray(),
                        '',
                        'id = '.$itemLocation['id'],
                    );
                }
            }

            // handle image uploading
            if (isset($data['images'])) {
                foreach ($data['images'] as $image) {
                    if (! Str::startsWith($image, 'https://') || ! Str::startsWith($image, 'http://')) {
                        $imageNames[] = [
                            'image' => uploadBase64($image, $this->imageFolder),
                        ];
                    }
                }
                $imageNames = collect($imageNames)->filter(function ($filter) {
                    return $filter['image'];
                })->values()->toArray();

                if (count($imageNames) > 0) {
                    $inventory->images()->createMany($imageNames);
                }
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
                    deleteImage(public_path('storage/'.$this->imageFolder.'/'.$imageName['image']));
                }
            }

            DB::rollBack();

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

    /**
     * Delete bulk data
     */
    public function bulkDeleteCustomInventory(array $ids): array
    {
        DB::beginTransaction();
        try {
            $this->customItemRepo->bulkDelete($ids, 'uid');

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

    public function getInventoriesTree(array $payload = [])
    {
        $where = "";

        if (!empty($payload)) {
            // if 'all' is not in the payload
            if (!in_array('all', $payload['type_id'])) {
                $payload = collect($payload['type_id'])->map(function ($item) {
                    return $this->generalService->getIdFromUid($item, new InventoryType());
                })->implode(',');

                $where = "item_type IN ({$payload})";
            }
        }

        $data = $this->repo->list(
            select: 'id,name,item_type,brand_id,supplier_id,unit_id',
            where: $where,
            relation: [
                'items:id,inventory_id,inventory_code,current_location,purchase_price,warranty,year_of_purchase',
                'brand:id,name',
                'itemTypeRelation:id,name',
                'supplier:id,name'
            ]
        );

        $output = [];

        foreach ($data as $inventory) {
            foreach ($inventory->items as $item) {
                $output[] = [
                    'name' => $inventory->name,
                    'code' => $item->inventory_code,
                    'brand' => $inventory->brand->name,
                    'supplier' => $inventory->supplier ? $inventory->supplier->name : '-',
                    'item_type' => $inventory->itemTypeRelation ? $inventory->itemTypeRelation->name : '-',
                    'purchase_price' => "Rp" . number_format($item->purchase_price, 0, ',', '.'),
                    'purchase_price_raw' => $item->purchase_price,
                    'year_of_purchase' => $item->year_of_purchase,
                ];
            }
        }

        $perBrands = collect($output)->groupBy('brand')->map(function ($brand) {
            return [
                'total_item' => $brand->count(),
                'total_price' => "Rp" . number_format($brand->sum('purchase_price_raw'), 0, ',', '.'),
                'total_price_raw' => $brand->sum('purchase_price_raw'),
                'items' => $brand,
            ];
        });

        $perItemType = collect($output)->groupBy('item_type')->map(function ($itemType) {
            return [
                'total_item' => $itemType->count(),
                'name' => $itemType[0]['item_type'],
                'total_price' => "Rp" . number_format($itemType->sum('purchase_price_raw'), 0, ',', '.'),
                'items' => $itemType,
            ];
        });

        $perYear = collect($output)->groupBy('year_of_purchase')->map(function ($year) {
            return [
                'total_item' => $year->count(),
                'total_price' => "Rp" . number_format($year->sum('purchase_price_raw'), 0, ',', '.'),
                'items' => $year,
            ];
        });

        return [
            'total_price' => "Rp" . number_format(collect($output)->sum('purchase_price_raw'), 0, ',', '.'),
            'total_items' => collect($output)->count(),
            'inventories' => $output,
            'per_brand' => $perBrands,
            'per_item' => $perItemType,
            'per_year' => $perYear,
        ];
    }

    /**
     * Export inventory data
     * 
     * @return array
     */
    public function export(array $payload): array
    {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();

            $path = 'inventory/report/';
            $filename = 'inventory_report_' . now() . '.xlsx';
            $filepath = $path . $filename;
            $downloadPath = \Illuminate\Support\Facades\URL::signedRoute(
                name: 'inventory.download.export.inventoryReport',
                parameters: [
                    'fp' => $filepath
                ],
                expiration: now()->addHours(5)
            );

            $data = $this->getInventoriesTree($payload);

            (new SummaryInventoryReport($data, $user, $downloadPath))->queue($filepath, 'public')->chain([
                new InventoryExportHasBeenCompleted($user, $downloadPath)
            ]);

            return generalResponse(
                message: "Your data is being processed. You'll rerceive a notification when the process is complete. You can check your inbox periodically to see the results",
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}
