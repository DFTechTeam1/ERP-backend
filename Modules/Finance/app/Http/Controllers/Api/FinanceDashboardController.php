<?php

namespace Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Finance\Services\FinanceDashboardService;

class FinanceDashboardController extends Controller
{
    public function __construct(
        private readonly FinanceDashboardService $service,
    ) {}

    /**
     * Pending invoice edit requests awaiting Finance approval.
     */
    public function pendingInvoiceUpdates(Request $request): JsonResponse
    {
        return apiResponse($this->service->getPendingInvoiceUpdates(
            limit: $request->integer('limit') ?: 10,
        ));
    }

    /**
     * Pending price-change requests awaiting Finance approval.
     */
    public function pendingPriceChanges(Request $request): JsonResponse
    {
        return apiResponse($this->service->getPendingPriceChanges(
            limit: $request->integer('limit') ?: 10,
        ));
    }
}
