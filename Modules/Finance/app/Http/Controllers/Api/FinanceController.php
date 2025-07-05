<?php

namespace Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Finance\Http\Requests\Transaction\Create;
use Modules\Finance\Services\TransactionService;

class FinanceController extends Controller
{
    private $service;

    public function __construct(
        TransactionService $service
    )
    {
        $this->service = $service;
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
     * @param Create $request       With this following structure
     * - string|float $payment_amount
     * - string $transaction_date
     * - string $invoice_id
     * - ?string $note
     * - ?string $reference
     * - array $images              With this following structure
     *      - ?object|binary $image
     * @param string $quotationId
     */
    public function createTransaction(Create $request, string $projectDealUid): JsonResponse
    {
        return apiResponse($this->service->store($request->all(), $projectDealUid));
    }

    /**
     * Generated signed url invoice
     * 
     * @param array $payload            With this following structure:
     * - string $uid
     * - string $type (bill or current)
     * - string $amount
     * - string $date
     * - string $output (stream or download)
     * 
     * @return JsonResponse
     */
    public function downloadInvoice(Request $request): JsonResponse
    {
        return apiResponse($this->service->downloadInvoice(payload: $request->all()));
    }

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
}
