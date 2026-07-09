<?php

namespace Modules\Production\Http\Controllers;

use App\Data\Production\Lead\CancelLeadData;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Production\Services\ProjectLeadService;

class ProjectLeadController extends Controller
{
    public function __construct(
        private readonly ProjectLeadService $service
    ) {}

    /**
     * Cancel project lead data
     *
     * @param CancelLeadData $request
     * @param string $projectLeadUid
     * @return JsonResponse
     */
    public function cancel(CancelLeadData $request, string $projectLeadUid): JsonResponse
    {
        return apiResponse($this->service->cancel($request, $projectLeadUid));
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
