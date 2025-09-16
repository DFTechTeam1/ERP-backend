<?php

namespace Modules\Company\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Company\Services\SettingService;

class SettingController extends Controller
{
    private $service;

    public function __construct(SettingService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //

        return response()->json([]);
    }

    public function getSetting($code = null)
    {
        return apiResponse($this->service->getSetting($code));
    }

    public function storeSetting(Request $request, $code = null): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->store($request->all(), $code));
    }

    public function getSettingByKeyAndCode(string $code, string $key)
    {
        return apiResponse($this->service->getSettingByKeyAndCode($key, $code));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //

        return response()->json([]);
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

    /**
     * Get all price calculation formula
     */
    public function getPriceCalculation(): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->getPriceCalculation());
    }
}
