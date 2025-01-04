<?php

namespace Modules\Company\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Company\Services\MasterService;

class CompanyController extends Controller
{
    private $masterService;

    public function __construct(
        MasterService $masterService
    )
    {
        $this->masterService = $masterService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //

        return response()->json([]);
    }

    /**
     * Get all religions
     *
     */
    public function getReligions()
    {
        return apiResponse($this->masterService->getReligions());
    }

    /**
     * Get all genders
     *
     */
    public function getGenders()
    {
        return apiResponse($this->masterService->getGenders());
    }

    /**
     * Get all martial status
     *
     */
    public function getMartialStatus()
    {
        return apiResponse($this->masterService->getMartialStatus());
    }

    /**
     * Get all blood type
     *
     */
    public function getBloodType()
    {
        return apiResponse($this->masterService->getBloodType());
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
}
