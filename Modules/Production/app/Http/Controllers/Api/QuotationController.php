<?php

namespace Modules\Production\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Modules\Production\Services\ProjectQuotationService;
use Modules\Production\Services\QuotationItemService;

class QuotationController extends Controller
{
    private $service;

    private $projectQuotationService;

    public function __construct(
        QuotationItemService $service,
        ProjectQuotationService $projectQuotationService
    ) {
        $this->service = $service;

        $this->projectQuotationService = $projectQuotationService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return apiResponse($this->service->list(select: 'id as value,name as title'));
    }

    public function pagination(): JsonResponse
    {
        return apiResponse($this->service->pagination(
            select: 'id as uid,name',
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(\Modules\Production\Http\Requests\Quotation\Store $request)
    {
        return apiResponse($this->service->store($request->toArray()));
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
    public function update(\Modules\Production\Http\Requests\Quotation\Store $request, $id)
    {
        return apiResponse($this->service->update(data: $request->validated(), id: $id));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {
        return apiResponse($this->service->delete(id: $id));
    }

    /**
     * Download or stream the quotation (PDF)
     * 
     * @param string $quoatationId
     * @param string $type
     * 
     * @return JsonResponse|Response
     */
    public function quotation(string $quotationId, string $type): JsonResponse|Response
    {
        return $this->projectQuotationService->generateQuotation(quotationId: $quotationId, type: $type);
    }

    public function invoice(string $transactionUid, string $type)
    {
        return $this->projectQuotationService->generateInvoice($transactionUid, $type);
    }

    public function generateInvoiceFromDeal(string $projectDealUid, string $type)
    {
        return $this->projectQuotationService->generateInvoiceFromDeal($projectDealUid, $type);
    }
}
