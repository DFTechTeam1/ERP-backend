<?php

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Inventory\Http\Requests\Inventory\AddStock;
use Modules\Inventory\Http\Requests\Inventory\Create;
use Modules\Inventory\Http\Requests\Inventory\Update;
use Modules\Inventory\Services\InventoryService;

class InventoryController extends Controller
{
    private $service;

    public function __construct(
        InventoryService $service
    ) {
        $this->service = $service;
    }

    public function downloadBrandTemplate()
    {
        return $this->service->createBrandTemplate();
    }

    public function downloadSupplierTemplate()
    {
        return $this->service->createSupplierTemplate();
    }

    public function downloadUnitTemplate()
    {
        return $this->service->createUnitTemplate();
    }

    public function downloadInventoryTypeTemplate()
    {
        return $this->service->createInventoryTypeTemplate();
    }

    public function downloadInventoryTemplate()
    {
        return $this->service->createExcelTemplate();
    }

    public function import(Request $request)
    {
        return apiResponse($this->service->import($request->toArray()));
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return apiResponse($this->service->list(
            'id,uid,name,item_type,brand_id,supplier_id,description,year_of_purchase,unit_id,purchase_price,warranty',
            '',
            [
                'items:id,inventory_code,status,inventory_id,current_location,purchase_price,warranty,year_of_purchase,qrcode',
                'image:id,image,inventory_id',
                'brand:id,name',
                'unit:id,name',
            ]
        ));
    }

    public function getAll()
    {
        return apiResponse($this->service->getAll());
    }

    /**
     * Add more stock to selected product
     *
     * @param  string  $uid
     */
    public function addStock(AddStock $request, $uid)
    {
        return apiResponse($this->service->addStock($request->validated(), $uid));
    }

    public function itemList($uid)
    {
        return apiResponse($this->service->itemList($uid));
    }

    public function getEquipmentForProjectRequest(): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->getEquipmentForProjectRequest());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Create $request)
    {
        $payload = $request->validated();
        if ((isset($payload['purchase_price'])) && (! empty($payload['purchase_price'])) && ($payload['purchase_price'] > 0)) {
            $price = str_replace(',', '', $payload['purchase_price']);
            $payload['purchase_price'] = $price;
        }

        return apiResponse($this->service->store($payload));
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return apiResponse($this->service->show($id));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Update $request, $id)
    {
        $payload = $request->validated();
        if ((isset($payload['purchase_price'])) && (! empty($payload['purchase_price'])) && ($payload['purchase_price'] > 0)) {
            $price = str_replace(',', '', $payload['purchase_price']);
            $payload['purchase_price'] = $price;
        }

        return apiResponse($this->service->update($payload, $id));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        //

        return response()->json([]);
    }

    /**
     * Bulk Delete
     */
    public function bulkDelete(Request $request)
    {
        return apiResponse($this->service->bulkDelete(
            collect($request->ids)->map(function ($item) {
                return $item['uid'];
            })->toArray()
        ));
    }

    /**
     * Bulk Delete
     */
    public function bulkDeleteCustomInventory(Request $request)
    {
        return apiResponse($this->service->bulkDeleteCustomInventory(
            collect($request->ids)->map(function ($item) {
                return $item['uid'];
            })->toArray()
        ));
    }

    /**
     * Gel related project with request equipments
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function requestEquipmentList()
    {
        return apiResponse($this->service->requestEquipmentList());
    }
}
