<?php

namespace App\Http\Controllers\Api;

use App\Enums\ErrorCode\Code;
use App\Http\Controllers\Controller;
use App\Services\AuthenticationLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthenticationLogController extends Controller
{
    public function __construct(
        private AuthenticationLogService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (! $this->service->isAllowed()) {
            return $this->forbidden();
        }

        return apiResponse(
            $this->service->list($request->all())
        );
    }

    public function show(int $id): JsonResponse
    {
        if (! $this->service->isAllowed()) {
            return $this->forbidden();
        }

        $log = $this->service->detail($id);

        if (! $log) {
            return apiResponse(
                errorResponse('Authentication log not found', [], Code::NotFound->value)
            );
        }

        return apiResponse(
            generalResponse(
                message: 'success',
                data: $log,
            )
        );
    }

    public function summary(Request $request): JsonResponse
    {
        if (! $this->service->isAllowed()) {
            return $this->forbidden();
        }

        return apiResponse(
            generalResponse(
                message: 'success',
                data: $this->service->summary($request->all()),
            )
        );
    }

    private function forbidden(): JsonResponse
    {
        return apiResponse(
            errorResponse(
                "You don't have permission to access this resource.",
                [],
                Code::Forbidden->value,
            )
        );
    }
}
