<?php

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Inventory\Http\Requests\UserInventory\AddItem;
use Modules\Inventory\Http\Requests\UserInventory\Create;
use Modules\Inventory\Http\Requests\UserInventory\Update;
use Modules\Inventory\Services\UserInventoryService;

class UserInventoryController extends Controller
{
    private $service;

    public function __construct()
    {
        $this->service = new UserInventoryService();
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return apiResponse($this->service->list(
            '*',
            '',
            [
                'items:inventory_id,user_inventory_master_id',
                'items.inventory:id,inventory_id,inventory_code,qrcode',
                'items.inventory.inventory:id,name,uid',
                'employee:id,name,uid'
            ]
        ));
    }

    public function getAvailableInventories(string $employeeUid)
    {
        return apiResponse($this->service->getAvailableInventories($employeeUid));
    }

    public function getUserInformation(string $employeeUid)
    {
        return apiResponse($this->service->getUserInformation($employeeUid));
    }

    /**
     * @param AddItem $request
     * @param string $uid
     * @return \Illuminate\Http\JsonResponse
     */
    public function addItem(AddItem $request, string $uid)
    {
        return apiResponse($this->service->addUserInventory($request->validated(), $uid));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Create $request)
    {
        return apiResponse($this->service->store($request->validated()));
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
    public function update(Update $request, string $id)
    {
        return apiResponse($this->service->update($request->validated(), $id));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        //

        return response()->json([]);
    }
}
