<?php

namespace Modules\Company\Http\Controllers;

use App\Enums\Company\ExportImportAreaType;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Company\Services\CompanyService;
use Modules\Company\Services\MasterService;

class CompanyController extends Controller
{
    private $masterService;

    private $companyService;

    public function __construct(
        MasterService $masterService,
        CompanyService $companyService
    ) {
        $this->masterService = $masterService;

        $this->companyService = $companyService;
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
     */
    public function getReligions()
    {
        return apiResponse($this->masterService->getReligions());
    }

    /**
     * Get tax configuration
     */
    public function getTaxConfiguration(): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->masterService->getTaxConfiguration());
    }

    /**
     * Get employee tax status
     */
    public function getEmployeeTaxStatus(): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->masterService->getEmployeeTaxStatus());
    }

    /**
     * Get jht configuration
     */
    public function getJhtConfiguration(): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->masterService->getJhtConfiguration());
    }

    /**
     * Get overtime status
     */
    public function getOvertimeStatus(): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->masterService->getOvertimeStatus());
    }

    /**
     * Get BPJS Kesehatan config
     */
    public function getBpjsKesehatanConfig(): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->masterService->getBpjsKesehatanConfig());
    }

    /**
     * Get JP Configuration
     */
    public function getJpConfiguration(): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->masterService->getJpConfiguration());
    }

    /**
     * Get All configuration
     */
    public function getAllConfiguration(): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->masterService->getAllConfiguration());
    }

    /**
     * Get all genders
     */
    public function getGenders()
    {
        return apiResponse($this->masterService->getGenders());
    }

    /**
     * Get all banks
     */
    public function getBanks()
    {
        return apiResponse($this->masterService->getBanks());
    }

    /**
     * Get all martial status
     */
    public function getMartialStatus()
    {
        return apiResponse($this->masterService->getMartialStatus());
    }

    /**
     * Get all relation family
     */
    public function getRelationFamily()
    {
        return apiResponse($this->masterService->getRelationFamily());
    }

    /**
     * Get all blood type
     */
    public function getBloodType()
    {
        return apiResponse($this->masterService->getBloodType());
    }

    /**
     * Get all level staff
     */
    public function getLevelStaff()
    {
        return apiResponse($this->masterService->getLevelStaff());
    }

    /**
     * Get all salary type
     */
    public function getSalaryType()
    {
        return apiResponse($this->masterService->getSalaryType());
    }

    /**
     * Get salary configuration
     */
    public function getSalaryConfiguration(): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->masterService->getSalaryConfiguration());
    }

    public function getBpjsKesehatanConfiguration()
    {
        //
    }

    /**
     * Get all ptkp types
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

    /**
     * Here we get all information about export and import result from table export_import_results
     * 
     * We serve in the table with pagination
     * 
     * @return JsonResponse
     */
    public function loadInboxData(): JsonResponse
    {
        $type = request('type', ExportImportAreaType::OldArea->value); // default is old_area
        return apiResponse($this->companyService->getInboxData($type));
    }

    public function clearInboxData(): JsonResponse
    {
        $type = request('type', ExportImportAreaType::OldArea->value); // default is old_area
        return apiResponse($this->companyService->clearInboxData($type));
    }
}
