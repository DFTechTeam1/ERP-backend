<?php

namespace Modules\Production\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Production\Http\Requests\Deals\AddInteractive;
use Modules\Production\Services\InteractiveProjectService;
use Modules\Production\Services\ProjectDealService;

class InteractiveController extends Controller
{
    private InteractiveProjectService $service;

    private ProjectDealService $projectDealService;

    public function __construct(
        InteractiveProjectService $service,
        ProjectDealService $projectDealService
    ) {
        $this->service = $service;
        $this->projectDealService = $projectDealService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return apiResponse($this->service->list());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AddInteractive $request, string $projectDealUid)
    {
        return apiResponse(
            $this->projectDealService->addInteractive(
                projectDealUid: $projectDealUid,
                payload: $request->validated()
            )
        );
    }

    /**
     * Approve interactive request
     */
    public function approveInteractive(): JsonResponse
    {
        $requestId = request('requestId');

        return apiResponse($this->projectDealService->approveInteractiveRequest(requestId: $requestId));
    }

    /**
     * Reject interactive request
     */
    public function rejectInteractiveRequest(): JsonResponse
    {
        $requestId = request('requestId');

        return apiResponse($this->projectDealService->rejectInteractiveRequest(requestId: $requestId));
    }

    /**
     * Show the specified resource.
     */
    public function show(string $uid)
    {
        return apiResponse($this->service->show(uid: $uid));
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

    public function getTeamList(): JsonResponse
    {
        return apiResponse($this->service->getTeamList());
    }
}
