<?php

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Inventory\Http\Requests\RequestInventory\ConvertToInventory;
use Modules\Inventory\Http\Requests\RequestInventory\Create;
use Modules\Inventory\Http\Requests\RequestInventory\Update;
use Modules\Inventory\Services\RequestInventoryService;

class RequestInventoryController extends Controller
{
    private $service;

    public function __construct()
    {
        $this->service = new RequestInventoryService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return apiResponse($this->service->list());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Create $request)
    {
        return apiResponse($this->service->store($request->validated()));
    }

    public function getApprovalLines()
    {
        return apiResponse($this->service->getApprovalLines());
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

    public function closedRequest(Requet $request)
    {
        return apiResponse($this->service->closedRequest($request->toArray()));
    }

    public function getRequestInventoryStatus()
    {
        return apiResponse($this->service->getRequestInventoryStatus());
    }

    public function processRequest(string $type, string $uid)
    {
        return apiResponse($this->service->process($type, $uid));
    }

    public function convertToInventory(ConvertToInventory $request, string $uid)
    {
        return apiResponse($this->service->convertToInventory($request->validated(), $uid));
    }

    public function bulkDelete(Request $request)
    {
        return apiResponse($this->service->bulkDelete(
            collect($request->ids)->map(function ($item) {
                return $item['uid'];
            })->toArray()
        ));
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
