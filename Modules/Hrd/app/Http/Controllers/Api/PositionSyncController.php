<?php

namespace Modules\Hrd\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Company\Data\Position\PositionSyncData;
use Modules\Company\Services\PositionSyncService;

class PositionSyncController extends Controller
{
    public function __construct(private PositionSyncService $service) {}

    /**
     * Read-only diff of Greatday positions vs the ERP (new / changed / gone).
     */
    public function preview(): JsonResponse
    {
        return apiResponse($this->service->preview());
    }

    /**
     * Apply the user-confirmed create / update / delete actions.
     */
    public function sync(PositionSyncData $data): JsonResponse
    {
        return apiResponse($this->service->apply($data));
    }
}
