<?php

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Inventory\Http\Requests\Brand\Create;
use Modules\Inventory\Http\Requests\Brand\Update;
use Modules\Inventory\Services\BrandService;

class BrandController extends Controller
{
    private $service;

    public function __construct()
    {
        $this->service = new BrandService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = $this->service->list(
            'uid,name'
        );

        return apiResponse($data);
    }

    public function allList()
    {
        return apiResponse($this->service->allList('uid as value,name as title'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Create $request)
    {
        return apiResponse($this->service->store($request->validated()));
    }

    public function import(Request $request)
    {
        return apiResponse($this->service->import($request->toArray()));
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

    public function bulkDelete(Request $request)
    {
        return apiResponse($this->service->bulkDelete(
            collect($request->ids)->map(function ($item) {
                return $item['uid'];
            })->toArray()
        ));
    }
}
