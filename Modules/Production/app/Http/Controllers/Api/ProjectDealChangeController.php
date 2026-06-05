<?php

namespace Modules\Production\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Production\Http\Requests\Deals\RequestFinalChange;
use Modules\Production\Services\ProjectDealChangeMcpService;

class ProjectDealChangeController extends Controller
{
    public function __construct(
        private readonly ProjectDealChangeMcpService $service,
    ) {}

    /**
     * Submit a change request for a FINAL project deal (friendly partial payload).
     * The change is queued for human approval and does NOT take effect immediately.
     */
    public function requestFinalDealChange(RequestFinalChange $request, string $projectDealUid): JsonResponse
    {
        return apiResponse($this->service->requestFinalDealChange(payload: $request->validated(), projectDealUid: $projectDealUid));
    }

    /**
     * List change requests and their approval status for a project deal.
     */
    public function getFinalDealChanges(string $projectDealUid): JsonResponse
    {
        return apiResponse($this->service->listFinalDealChanges(projectDealUid: $projectDealUid));
    }
}
