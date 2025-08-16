<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Modules\Production\Services\ProjectDealService;

class FinanceController extends Controller
{
    private ProjectDealService $projectDealService;

    public function __construct(ProjectDealService $projectDealService)
    {
        $this->projectDealService = $projectDealService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('finance::index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('finance::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('finance::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('finance::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        //
    }

    public function downloadFinanceReport()
    {
        $filename = request('fp');

        return \Illuminate\Support\Facades\Storage::download($filename);
    }

    public function approvePriceChanges()
    {
        $priceChangeId = request('priceChangeId');
        
        $response = $this->projectDealService->approvePriceChanges(
            priceChangeId: $priceChangeId,
        );

        if (!$response['error']) {
            return view('invoices.approved', [
                'title' => 'Approve Price Changes',
                'message' => "Price changes approved successfully.",
            ]);
        }

        abort(400);
    }

    public function rejectPriceChanges()
    {
        $priceChangeId = request('priceChangeId');

        $response = $this->projectDealService->rejectPriceChanges(
            priceChangeId: $priceChangeId,
        );

        if (!$response['error']) {
            return view('invoices.rejected', [
                'title' => 'Reject Price Changes',
                'message' => "Price changes rejected successfully.",
            ]);
        }

        abort(400);
    }
}
