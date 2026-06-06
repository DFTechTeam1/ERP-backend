<?php

namespace App\Http\Controllers\Api\Mcp;

use App\Http\Controllers\Controller;
use App\Services\McpAccessLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class McpAccessLogController extends Controller
{
    public function __construct(
        private McpAccessLogService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        return apiResponse(
            $this->service->list($request->all())
        );
    }

    public function show(int $id): JsonResponse
    {
        $log = $this->service->detail($id);

        if (! $log) {
            return apiResponse(
                errorResponse('Log not found')
            );
        }

        return apiResponse(
            generalResponse(
                message: 'success',
                data: $log->toArray(),
            )
        );
    }

    public function summary(Request $request): JsonResponse
    {
        return apiResponse(
            generalResponse(
                message: 'success',
                data: $this->service->summary($request->all()),
            )
        );
    }

    public function usage(Request $request): JsonResponse
    {
        return apiResponse(
            generalResponse(
                message: 'success',
                data: $this->service->usage($request->all()),
            )
        );
    }

    public function usageByRoute(Request $request): JsonResponse
    {
        return apiResponse(
            generalResponse(
                message: 'success',
                data: $this->service->usageByRoute($request->all()),
            )
        );
    }
}
