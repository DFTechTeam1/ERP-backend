<?php

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Inventory\Http\Requests\Inventory\Create;
use Modules\Inventory\Http\Requests\Inventory\Update;
use Modules\Inventory\Services\InventoryService;
use Modules\Inventory\Http\Requests\Inventory\AddStock;

class InventoryController extends Controller
{
    private $service;

    public function __construct()
    {
        $this->service = new InventoryService;
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
                'items:id,inventory_code,status,inventory_id,current_location',
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
     * @param AddStock $request
     * @param string $uid
     */
    public function addStock(AddStock $request, $uid)
    {
        return apiResponse($this->service->addStock($request->validated(), $uid));
    }

    public function itemList($uid)
    {
        return apiResponse($this->service->itemList($uid));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Create $request)
    {
        $payload = $request->validated();
        if ((isset($payload['purchase_price'])) && (!empty($payload['purchase_price'])) && ($payload['purchase_price'] > 0)) {
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
        if ((isset($payload['purchase_price'])) && (!empty($payload['purchase_price'])) && ($payload['purchase_price'] > 0)) {
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
     *
     * @param Request $request
     */
    public function bulkDelete(Request $request)
    {
        return apiResponse($this->service->bulkDelete(
            collect($request->ids)->map(function ($item) {
                return $item['uid'];
            })->toArray()
        ));
    }

    public function requestEquipmentList()
    {
        
    }
}
