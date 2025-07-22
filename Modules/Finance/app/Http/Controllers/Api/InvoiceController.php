<?php

namespace Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Finance\Http\Requests\Invoice\BillInvoice;
use Modules\Finance\Http\Requests\Invoice\EditInvoice;
use Modules\Finance\Http\Requests\Transaction\Create;
use Modules\Finance\Services\InvoiceService;

class InvoiceController extends Controller
{
    private $service;

    public function __construct(
        InvoiceService $service
    )
    {
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
    public function store(BillInvoice $request, string $projectDealUid)
    {

    }

    /**
     * Generate invoice to bill to customer
     * 
     * @param BillInvoice $request
     * @param string $projectDealUid
     * 
     * @return JsonResponse
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
     * @param EditInvoice $request
     * @param string $invoiceId
     * 
     * @return JsonResponse
     */
    public function updateTemporaryData(EditInvoice $request, string $invoiceId): JsonResponse
    {
        return apiResponse($this->service->updateTemporaryData(payload: $request->validated(), invoiceId: $invoiceId));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        //

        return response()->json([]);
    }

    public function downloadInvoice()
    {
        return $this->service->downloadInvoice();
    }

    public function downloadGeneralInvoice()
    {
        return $this->service->downloadGeneralInvoice();
    }
}
