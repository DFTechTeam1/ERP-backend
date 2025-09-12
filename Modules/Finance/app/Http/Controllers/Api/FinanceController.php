<?php

namespace Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Finance\Http\Requests\ExportProjectDealSummary;
use Modules\Finance\Http\Requests\ExportRequest;
use Modules\Finance\Http\Requests\PriceChanges;
use Modules\Finance\Http\Requests\Transaction\Create;
use Modules\Finance\Services\InvoiceService;
use Modules\Finance\Services\TransactionService;
use Modules\Production\Services\ProjectDealService;

class FinanceController extends Controller
{
    private $service;

    private $invoiceService;

    private ProjectDealService $projectDealService;

    public function __construct(
        TransactionService $service,
        InvoiceService $invoiceService,
        ProjectDealService $projectDealService
    ) {
        $this->service = $service;
        $this->invoiceService = $invoiceService;
        $this->projectDealService = $projectDealService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //

        return response()->json([]);
    }

    /**
     * Create customer transaction
     *
     * @param  Create  $request  With this following structure
     *                           - string|float $payment_amount
     *                           - string $transaction_date
     *                           - string $invoice_id
     *                           - ?string $note
     *                           - ?string $reference
     *                           - array $images              With this following structure
     *                           - ?object|binary $image
     * @param  string  $quotationId
     */
    public function createTransaction(Create $request, string $projectDealUid): JsonResponse
    {
        return apiResponse($this->service->store($request->all(), $projectDealUid));
    }

    /**
     * Generated signed url invoice
     *
     * @param  array  $payload  With this following structure:
     *                          - string $uid
     *                          - string $type (bill or current)
     *                          - string $amount
     *                          - string $date
     *                          - string $output (stream or download)
     */
    public function downloadInvoice(Request $request): JsonResponse
    {
        return apiResponse($this->service->downloadInvoice(payload: $request->all()));
    }

    public function export(ExportRequest $request) {}

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //

        return response()->json([]);
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        //

        return response()->json([]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        //

        return response()->json([]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        //

        return response()->json([]);
    }

    /**
     * Here we'll export project deals summary based on user selection
     *
     * @param  ExportProjectDealSummary  $request  With these following structure:
     *                                             - string $date_range
     *                                             - array $marketings
     *                                             - array $status
     *                                             - array $price
     */
    public function exportFinanceData(ExportProjectDealSummary $request): JsonResponse
    {
        return apiResponse($this->invoiceService->exportFinanceData(payload: $request->validated()));
    }

    /**
     * Request price changes for project deal
     *
     * @param  PriceChanges  $request  With this following structure:
     *                                 - int $price
     *                                 - string $reason
     */
    public function requestPriceChanges(PriceChanges $request, string $projectDealUid): JsonResponse
    {
        return apiResponse($this->projectDealService->requestPriceChanges(
            payload: $request->validated(),
            projectDealUid: $projectDealUid
        ));
    }

    /**
     * Approve price changes for project deal
     */
    public function approvePriceChanges(string $projectDealUid, string $changeId): JsonResponse
    {
        return apiResponse($this->projectDealService->approvePriceChanges(priceChangeId: $changeId));
    }

    /**
     * Reject price changes for project deal
     */
    public function rejectPriceChanges(Request $request, string $projectDealUid, string $changeId): JsonResponse
    {
        return apiResponse($this->projectDealService->rejectPriceChanges(
            priceChangeId: $changeId,
            reason: $request->input('reason', '')
        ));
    }

    /**
     * Get price change reasons
     */
    public function getPriceChangeReasons(): JsonResponse
    {
        return apiResponse($this->projectDealService->getPriceChangeReasons());
    }
}
