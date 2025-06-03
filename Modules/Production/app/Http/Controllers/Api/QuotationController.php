<?php

namespace Modules\Production\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Production\Services\QuotationItemService;

class QuotationController extends Controller
{
    private $service;

    public function __construct(QuotationItemService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return apiResponse($this->service->list(select: 'id as value,name as title'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(\Modules\Production\Http\Requests\Quotation\Store $request)
    {
        return apiResponse($this->service->store($request->toArray()));
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        //

        return response()->json([]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        //

        return response()->json([]);
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
