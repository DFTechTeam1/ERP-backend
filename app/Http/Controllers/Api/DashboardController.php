<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Hrd\Services\EmployeeService;

class DashboardController extends Controller
{
    private $service;

    private $employeeService;

    public function __construct(
        \App\Services\DashboardService $service,
        EmployeeService $employeeService
    )
    {
        $this->service = $service;

        $this->employeeService = $employeeService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    public function getReport()
    {
        return apiResponse($this->service->getReport());
    }

    public function getProjectSong(): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->getProjectSong());
    }

    public function needCompleteProject()
    {
        return apiResponse($this->service->needCompleteProject());
    }

    public function getProjectCalendar()
    {
        return apiResponse($this->service->getProjectCalendars());
    }

    public function getProjectDeadline()
    {
        return apiResponse($this->service->getProjectDeadline());
    }

    public function getHrReport(string $type)
    {
        $type = ucfirst($type);
        $function = "get{$type}Report";

        return apiResponse($this->employeeService->{$function}());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
