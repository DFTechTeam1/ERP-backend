<?php

namespace Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Finance\Http\Requests\ExportProjectDealSummary;
use Modules\Finance\Http\Requests\ExportRequest;
use Modules\Finance\Http\Requests\PriceChanges;
use Modules\Finance\Http\Requests\Transaction\Create;
use Modules\Finance\Services\FinanceInsightService;
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
        ProjectDealService $projectDealService,
        private readonly FinanceInsightService $financeInsightService
    ) {
        $this->service = $service;
        $this->invoiceService = $invoiceService;
        $this->projectDealService = $projectDealService;
    }

    /**
     * Comprehensive, role-aware finance insight for MCP analysis.
     *
     * Access is resolved from the authenticated user's ROLE (director/root,
     * finance, marketing) — not the MCP permission.
     */
    public function getFinanceInsight(): JsonResponse
    {
        return apiResponse($this->financeInsightService->getInsight());
    }

    /**
     * Outstanding receivables drill-down (director/root & finance only).
     */
    public function getFinanceReceivables(): JsonResponse
    {
        return apiResponse($this->financeInsightService->getReceivables());
    }

    /**
     * Per-marketing performance leaderboard (director/root only).
     */
    public function getMarketingPerformance(): JsonResponse
    {
        return apiResponse($this->financeInsightService->getMarketingPerformance());
    }

    /**
     * Top revenue deals drill-down (scope follows the role).
     */
    public function getTopDeals(): JsonResponse
    {
        return apiResponse($this->financeInsightService->getTopDeals());
    }

    /**
     * Get all transactions
     */
    public function index(): JsonResponse
    {
        return apiResponse($this->service->list());
    }

    /**
     * Get transaction summary
     */
    public function getTransactionSummary(): JsonResponse
    {
        return apiResponse($this->service->getTransactionSummary());
    }

    /**
     * Get monthly income and outcome trend for the last 12 months.
     */
    public function getMonthlyTrend(): JsonResponse
    {
        return apiResponse($this->service->getMonthlyTrend());
    }

    /**
     * Get top income sources ordered by payment amount.
     */
    public function getTopSources(): JsonResponse
    {
        return apiResponse($this->service->getTopSources());
    }

    /**
     * Get outstanding invoice summary.
     */
    public function getOutstandingData(): JsonResponse
    {
        return apiResponse($this->service->getOutstandingData());
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
    public function show($uid)
    {
        return apiResponse($this->service->show($uid));
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
