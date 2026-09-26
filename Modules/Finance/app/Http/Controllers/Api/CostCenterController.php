<?php

namespace Modules\Finance\Http\Controllers\Api;

use App\Data\Finance\CostCenter\StoreCostCenterData;
use App\Data\Finance\CostCenter\UpdateCostCenterData;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Finance\Services\CostCenterService;

class CostCenterController extends Controller
{
    /**
     * @param  CostCenterService  $service  Service handling cost center reads and writes.
     */
    public function __construct(
        private readonly CostCenterService $service
    ) {}

    /**
     * List cost centers (paginated, filtered by the request query string).
     *
     * @return JsonResponse A paginated list of ListCostCenterData wrapped in the API envelope.
     */
    public function index(): JsonResponse
    {
        return apiResponse($this->service->list());
    }

    /**
     * Create a new cost center.
     *
     * @param  StoreCostCenterData  $request  The validated create payload.
     * @return JsonResponse The API envelope for the create result.
     */
    public function store(StoreCostCenterData $request): JsonResponse
    {
        return apiResponse($this->service->store($request));
    }

    /**
     * Update an existing cost center.
     *
     * @param  UpdateCostCenterData  $request  The validated update payload.
     * @param  string  $costCenterUid  The uid of the cost center to update.
     * @return JsonResponse The API envelope for the update result.
     */
    public function update(UpdateCostCenterData $request, string $costCenterUid): JsonResponse
    {
        return apiResponse($this->service->update($request, $costCenterUid));
    }

    /**
     * Toggle a cost center's active flag.
     *
     * @param  string  $costCenterUid  The uid of the cost center to toggle.
     * @return JsonResponse The API envelope for the toggle result.
     */
    public function toggleStatus(string $costCenterUid): JsonResponse
    {
        return apiResponse($this->service->toggleStatus($costCenterUid));
    }

    /**
     * Delete a cost center.
     *
     * @param  string  $costCenterUid  The uid of the cost center to delete.
     * @return JsonResponse The API envelope for the delete result.
     */
    public function destroy(string $costCenterUid): JsonResponse
    {
        return apiResponse($this->service->delete($costCenterUid));
    }
}
