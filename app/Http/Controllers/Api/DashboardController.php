<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private $service;

    public function __construct(\App\Services\DashboardService $service)
    {
        $this->service = $service;
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

    /**
     * Get list of project that need to be completed
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function needCompleteProject(): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->needCompleteProject());
    }

    /**
     * Get projects to be consumed by frontend calendar
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProjectCalendar(): \Illuminate\Http\JsonResponse
    {
        return apiResponse($this->service->getProjectCalendars());
    }

    public function getProjectDeadline()
    {
        return apiResponse($this->service->getProjectDeadline());
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
