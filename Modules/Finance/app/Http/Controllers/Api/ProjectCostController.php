<?php

namespace Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Finance\Services\ProjectCostService;

class ProjectCostController extends Controller
{
    /**
     * @param  ProjectCostService  $service  Service that builds the project cost dashboard data.
     */
    public function __construct(
        private readonly ProjectCostService $service
    ) {}

    /**
     * Unused resource stub.
     *
     * @return JsonResponse An empty JSON payload.
     */
    public function index()
    {
        //

        return response()->json([]);
    }

    /**
     * Return the full cost detail for a single project.
     *
     * @param  string  $projectUid  The project uid to load.
     * @return JsonResponse A DetailProjectCostData payload wrapped in the API envelope.
     */
    public function detailProjectCost(string $projectUid): JsonResponse
    {
        return apiResponse($this->service->detailProjectCost($projectUid));
    }

    /**
     * Return the "latest projects" widget (the five most recent projects for the filters).
     *
     * @return JsonResponse A list of ProjectItemData wrapped in the API envelope.
     */
    public function getDashboardLatest(): JsonResponse
    {
        return apiResponse($this->service->getDashboardLatest());
    }

    /**
     * Return the paginated project cost listing for the dashboard table.
     *
     * @return JsonResponse A ProjectListData payload wrapped in the API envelope.
     */
    public function getDashboard(): JsonResponse
    {
        return apiResponse($this->service->getDashboard());
    }

    /**
     * Return the dashboard summary card (totals and averages).
     *
     * @return JsonResponse A DashboardListSummaryData payload wrapped in the API envelope.
     */
    public function getDashboardSummary(): JsonResponse
    {
        return apiResponse($this->service->getDashboardSummary());
    }

    /**
     * Return the monthly cost trend.
     *
     * @return JsonResponse A list of DashboardCostTrendData wrapped in the API envelope.
     */
    public function getCostTrend(): JsonResponse
    {
        return apiResponse($this->service->getCostTrend());
    }

    /**
     * Return the cost breakdown grouped by event (project) class.
     *
     * @return JsonResponse A list of DashboardCostByClassData wrapped in the API envelope.
     */
    public function getCostByClass(): JsonResponse
    {
        return apiResponse($this->service->getCostByClass());
    }

    /**
     * Return the cost composition card (cost split by component).
     *
     * @return JsonResponse A list of composition components wrapped in the API envelope.
     */
    public function getCostComposition(): JsonResponse
    {
        return apiResponse($this->service->getCostComposition());
    }

    /**
     * Unused resource stub.
     *
     * @param  Request  $request  The incoming request.
     * @return JsonResponse An empty JSON payload.
     */
    public function store(Request $request)
    {
        //

        return response()->json([]);
    }

    /**
     * Unused resource stub.
     *
     * @param  int|string  $id  The resource identifier.
     * @return JsonResponse An empty JSON payload.
     */
    public function show($id)
    {
        //

        return response()->json([]);
    }

    /**
     * Unused resource stub.
     *
     * @param  Request  $request  The incoming request.
     * @param  int|string  $id  The resource identifier.
     * @return JsonResponse An empty JSON payload.
     */
    public function update(Request $request, $id)
    {
        //

        return response()->json([]);
    }

    /**
     * Unused resource stub.
     *
     * @param  int|string  $id  The resource identifier.
     * @return JsonResponse An empty JSON payload.
     */
    public function destroy($id)
    {
        //

        return response()->json([]);
    }
}
