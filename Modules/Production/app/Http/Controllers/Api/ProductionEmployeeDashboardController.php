<?php

namespace Modules\Production\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Production\Services\ProductionEmployeeDashboardService;

/**
 * Endpoints powering the Production Employee dashboard. All scoping is
 * done inside the service via Auth::user()->employee_id - the routes are
 * only gated by auth.session.
 */
class ProductionEmployeeDashboardController extends Controller
{
    public function __construct(
        private readonly ProductionEmployeeDashboardService $service,
    ) {}

    /** Personal KPI counters for the header row. */
    public function kpi(Request $request): JsonResponse
    {
        return apiResponse($this->service->getKpi(
            month: $request->integer('month') ?: null,
            year: $request->integer('year') ?: null,
        ));
    }

    /** My active task inbox (OnProgress + Revise). */
    public function myTasks(Request $request): JsonResponse
    {
        return apiResponse($this->service->getMyTasks(
            limit: $request->integer('limit') ?: 20,
        ));
    }

    /** Pool tasks awaiting a picker. */
    public function poolTasks(Request $request): JsonResponse
    {
        return apiResponse($this->service->getPoolTasks(
            limit: $request->integer('limit') ?: 20,
        ));
    }
}
