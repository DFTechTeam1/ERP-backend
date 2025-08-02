<?php

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Inventory\Http\Requests\UserInventory\AddItem;
use Modules\Inventory\Http\Requests\UserInventory\Create;
use Modules\Inventory\Http\Requests\UserInventory\DeleteInventory;
use Modules\Inventory\Http\Requests\UserInventory\Update;
use Modules\Inventory\Services\EmployeeInventoryMasterService;

class UserInventoryController extends Controller
{
    private $service;

    public function __construct()
    {
        $this->service = new EmployeeInventoryMasterService;
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
                'employee:id,name,nickname,uid',
                'items:id,employee_inventory_master_id,inventory_item_id,inventory_status,inventory_source,inventory_source_id',
                'items.inventory:id,inventory_id,inventory_code',
                'items.inventory.inventory:id,name',
            ]
        ));
    }

    public function getAvailableInventories(string $employeeUid)
    {
        return apiResponse($this->service->getAvailableInventories($employeeUid));
    }

    public function getAvailableCustomInventories(string $employeeUid)
    {
        return apiResponse($this->service->getAvailableCustomInventories($employeeUid));
    }

    public function getUserInformation(string $employeeUid)
    {
        return apiResponse($this->service->getUserInformation($employeeUid));
    }

    /**
     * @param  string  $uid
     * @return \Illuminate\Http\JsonResponse
     */
    public function addItem(AddItem $request, mixed $id)
    {
        return apiResponse($this->service->updateInventory($request->validated(), $id));
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

    public function deleteInventory(DeleteInventory $request)
    {
        return apiResponse($this->service->deleteInventory($request->validated()));
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
