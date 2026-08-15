<?php

namespace Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Finance\Services\MarketingDashboardService;

class MarketingDashboardController extends Controller
{
    public function __construct(
        private readonly MarketingDashboardService $service,
    ) {}

    /**
     * Pipeline funnel (Leads → Deals → Finalized → Fully paid).
     * Scoped to the authenticated Sales user; Director/Root see all
     * unless they narrow to one salesperson via `?sales_employee_id=`.
     */
    public function pipeline(Request $request): JsonResponse
    {
        return apiResponse($this->service->getPipeline(
            month: $request->integer('month') ?: null,
            year: $request->integer('year') ?: null,
            salesEmployeeId: $request->integer('sales_employee_id') ?: null,
        ));
    }

    /**
     * Active deals table for the given period, same scoping rules as pipeline.
     */
    public function deals(Request $request): JsonResponse
    {
        return apiResponse($this->service->getMyDeals(
            month: $request->integer('month') ?: null,
            year: $request->integer('year') ?: null,
            limit: $request->integer('limit') ?: 10,
            salesEmployeeId: $request->integer('sales_employee_id') ?: null,
        ));
    }

    /**
     * List of sales people (populates the executive filter dropdown).
     * Sales users get 403.
     */
    public function salesPeople(): JsonResponse
    {
        return apiResponse($this->service->getSalesPeople());
    }
}
