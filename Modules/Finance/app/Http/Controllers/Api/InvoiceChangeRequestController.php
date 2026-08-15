<?php

namespace Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Finance\Services\InvoiceChangeRequestService;

class InvoiceChangeRequestController extends Controller
{
    public function __construct(
        private readonly InvoiceChangeRequestService $service,
    ) {}

    /**
     * Paginated list, filterable by status tab (pending/approved/rejected/all).
     */
    public function index(Request $request): JsonResponse
    {
        return apiResponse($this->service->list(
            status: $request->query('status') ?: null,
            page: $request->integer('page') ?: 1,
            itemsPerPage: $request->integer('itemsPerPage') ?: 10,
        ));
    }

    /**
     * Approve a pending invoice change request by its numeric id.
     */
    public function approve(int $id): JsonResponse
    {
        return apiResponse($this->service->approve($id));
    }

    /**
     * Reject a pending invoice change request. Body: { reason?: string }.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        return apiResponse($this->service->reject(
            id: $id,
            reason: $request->input('reason'),
        ));
    }
}
