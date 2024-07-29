<?php

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use \Modules\Inventory\Http\Requests\Inventory\Custom\Build;
use Modules\Inventory\Http\Requests\Inventory\Custom\UpdateBuild;

class CustomInventoryController extends Controller
{
    private $service;

    public function __construct()
    {
        $this->service = new \Modules\Inventory\Services\InventoryService;
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
}
