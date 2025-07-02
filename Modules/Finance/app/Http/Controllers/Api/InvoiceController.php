<?php

namespace Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Finance\Http\Requests\Invoice\BillInvoice;
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
    public function index()
    {
        //

        return response()->json([]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(BillInvoice $request, string $projectDealUid)
    {

    }

    public function generateBillInvoice(BillInvoice $request, string $projectDealUid)
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
