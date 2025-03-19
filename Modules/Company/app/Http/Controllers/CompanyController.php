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
     * Get all relation family
     *
     */
    public function getRelationFamily()
    {
        return apiResponse($this->masterService->getRelationFamily());
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
     * Get all level staff
     *
     */
    public function getLevelStaff()
    {
        return apiResponse($this->masterService->getLevelStaff());
    }

    /**
     * Get all salary type
     *
     */
    public function getSalaryType()
    {
        return apiResponse($this->masterService->getSalaryType());
    }

    /**
    * Get salary configuration
    *
    * @return \Illuminate\Http\JsonResponse
    */
    public function getSalaryConfiguration(): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->masterService->getSalaryConfiguration());
    }

    public function getJhtConfiguration()
    {

    }

    public function getEmployeeTaxStatus()
    {

    }

    public function getJpConfiguration()
    {

    }

    public function getOvertimeStatus()
    {

    }

    public function getBpjsKesehatanConfiguration()
    {
        //
    }

    /**
     * Get all ptkp types
     *
     */
    public function getPtkpType()
    {
        return apiResponse($this->masterService->getPtkpType());
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
