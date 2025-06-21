<?php

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Inventory\Http\Requests\Inventory\Custom\Build;
use Modules\Inventory\Http\Requests\Inventory\Custom\UpdateBuild;
use Modules\Inventory\Services\CustomInventoryService;
use Modules\Inventory\Services\InventoryService;

class CustomInventoryController extends Controller
{
    private $service;

    private $customService;

    public function __construct(
        InventoryService $inventoryService,
        CustomInventoryService $customInventoryService
    ) {
        $this->service = $inventoryService;

        $this->customService = $customInventoryService;
    }

    public function getItemList()
    {
        return apiResponse($this->service->getItemListForCustomBuild());
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return apiResponse($this->service->listOfBuildInventories());
    }

    public function getAssembled()
    {
        return apiResponse($this->customService->getAssembled());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Build $request)
    {
        return apiResponse($this->service->storeBuildInventory($request->validated()));
    }

    /**
     * Show the specified resource.
     */
    public function show(string $uid)
    {
        return apiResponse($this->service->detailCustomInventory($uid));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBuild $request, string $uid)
    {
        return apiResponse($this->service->updateBuildInventory($request->validated(), $uid));
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
        return apiResponse($this->service->bulkDeleteCustomInventory(
            collect($request->ids)->map(function ($item) {
                return $item['uid'];
            })->toArray()
        ));
    }
}
