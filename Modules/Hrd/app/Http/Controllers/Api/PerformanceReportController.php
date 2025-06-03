<?php

namespace Modules\Hrd\Http\Controllers\Api;

use App\Enums\Employee\Status;
use App\Http\Controllers\Controller;
use App\Services\GeneralService;
use Illuminate\Http\Request;
use Modules\Hrd\Http\Requests\Employee\PerformanceReport;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Hrd\Services\EmployeePointService;
use Modules\Hrd\Services\PerformanceReportService;

class PerformanceReportController extends Controller
{
    private $service;

    private $generalService;

    private $employeeRepo;

    private $employeePointService;

    public function __construct(
        PerformanceReportService $service,
        GeneralService $generalService,
        EmployeeRepository $employeeRepo,
        EmployeePointService $employeePointService
    ) {
        $this->service = $service;

        $this->employeeRepo = $employeeRepo;

        $this->generalService = $generalService;

        $this->employeePointService = $employeePointService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //

        return response()->json([]);
    }

    public function performanceDetail(string $employeeId)
    {
        return apiResponse($this->service->performanceDetail($employeeId));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //

        return response()->json([]);
    }

    public function export(PerformanceReport $request)
    {

        // $employees = $this->employeeRepo->list(
        //     select: 'id,name,employee_id,position_id',
        //     where: "status NOT IN (". Status::Inactive->value .")",
        //     relation: [
        //         'position:id,name'
        //     ]
        // );

        // $employeeIds = collect($employees)->pluck('id')->toArray();

        // $data = [];

        // foreach ($employees as $employee) {
        //     $pointData = $this->employeePointService->renderEachEmployeePoint($employee->id, $startDate, $endDate) ?? [];

        //     if ($pointData) {
        //         $data[] = $pointData;
        //     } else {
        //         $data[] = [
        //             'employee' => $employee,
        //             'detail_projects' => []
        //         ];
        //     }
        // }

        return apiResponse($this->service->export($request->validated()));
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
