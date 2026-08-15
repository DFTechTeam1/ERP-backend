<?php

namespace Modules\Production\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Production\Services\ProjectManagerDashboardService;

class ProjectManagerDashboardController extends Controller
{
    public function __construct(
        private readonly ProjectManagerDashboardService $service,
    ) {}

    public function kpi(): JsonResponse
    {
        return apiResponse($this->service->getKpi());
    }

    public function myProjects(Request $request): JsonResponse
    {
        return apiResponse($this->service->getMyProjects(
            limit: $request->integer('limit') ?: 10,
        ));
    }

    public function teamWorkload(Request $request): JsonResponse
    {
        return apiResponse($this->service->getTeamWorkload(
            limit: $request->integer('limit') ?: 20,
        ));
    }

    public function atRiskProjects(Request $request): JsonResponse
    {
        return apiResponse($this->service->getAtRiskProjects(
            limit: $request->integer('limit') ?: 10,
        ));
    }
}
