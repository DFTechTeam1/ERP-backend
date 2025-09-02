<?php

namespace Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Finance\Http\Requests\Invoice\BillInvoice;
use Modules\Finance\Http\Requests\Invoice\EditInvoice;
use Modules\Finance\Services\InvoiceService;

class InvoiceController extends Controller
{
    private $service;

    public function __construct(
        InvoiceService $service
    ) {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(string $projectDealUid)
    {
        $projectDealId = \Illuminate\Support\Facades\Crypt::decryptString($projectDealUid);

        return apiResponse($this->service->list(
            select: 'id,parent_number,number,sequence,project_deal_id,amount,paid_amount,status',
            where: "project_deal_id = {$projectDealId} and is_main = 0"
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(BillInvoice $request, string $projectDealUid) {}

    /**
     * Generate invoice to bill to customer
     */
    public function generateBillInvoice(BillInvoice $request, string $projectDealUid): JsonResponse
    {
        return apiResponse($this->service->store(data: $request->validated(), projectDealUid: $projectDealUid));
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
     * Reject invoice changes.
     */
    public function rejectChanges(Request $request, string $projectDealUid, string $invoiceUid, string|int $pendingUpdateId): JsonResponse
    {
        return apiResponse($this->service->rejectChanges(payload: $request->all(), invoiceUid: $invoiceUid, pendingUpdateId: $pendingUpdateId));
    }

    /**
     * Approve invoice changes.
     */
    public function approveChanges(string $projectDealUid, string $invoiceUid, string|int $pendingUpdateId): JsonResponse
    {
        return apiResponse($this->service->approveChanges(invoiceUid: $invoiceUid, pendingUpdateId: $pendingUpdateId));
    }

    public function emailApproveChanges()
    {
        $response = $this->service->approveChanges(invoiceUid: request('invoiceUid'), fromExternalUrl: true, pendingUpdateId: request('cuid'));

        if (! $response['error']) {
            return redirect()->route('invoices.approved');
        }

        return response()->json($response);
    }

    public function emailRejectChanges()
    {
        $response = $this->service->rejectChanges(payload: [], invoiceUid: request('invoiceUid'), fromExternalUrl: true, pendingUpdateId: request('cid'));

        if (! $response['error']) {
            return redirect()->route('invoices.rejected');
        }

        return response()->json($response);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(EditInvoice $request, $id)
    {
        //

        return response()->json([]);
    }

    /**
     * Here we save temporary data for invoice update.
     * Need Director approval to change the invoice.
     *
     * @param  string  $invoiceId
     */
    public function updateTemporaryData(EditInvoice $request): JsonResponse
    {
        return apiResponse($this->service->updateTemporaryData(payload: $request->validated()));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $projectDealUid, string $id): JsonResponse
    {
        return apiResponse($this->service->delete(invoiceUid: $id));
    }

    public function downloadInvoice()
    {
        return $this->service->downloadInvoice();
    }

    /**
     * Download invoice based on type.
     * Type will be:
     * - general invoice
     * - collection invoice
     * - proof of payment invoice
     * - history invoice
     *
     *
     * @return JsonResponse
     */
    public function downloadInvoiceBasedOnType(string $type)
    {
        $payload = [
            'projectDealUid' => request('projectDealUid'),
            'amount' => request('amount'),
            'paymentDate' => request('paymentDate'),
            'invoiceUid' => request('invoiceUid'),
        ];

        return $this->service->downloadInvoiceBasedOnType(type: $type, payload: $payload);
    }

    public function downloadGeneralInvoice()
    {
        return $this->service->downloadGeneralInvoice();
    }
}
