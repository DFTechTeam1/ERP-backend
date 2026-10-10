<?php

namespace Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Finance\Services\AiCostService;

class AiCostController extends Controller
{
    /**
     * @param  AiCostService  $service  Service that builds the AI spending reports.
     */
    public function __construct(
        private readonly AiCostService $service
    ) {}

    /**
     * Return the AI spending summary (total cost in USD/IDR, tokens and action count).
     *
     * @return JsonResponse A SummaryData payload wrapped in the API envelope.
     */
    public function summary(): JsonResponse
    {
        return apiResponse($this->service->summary());
    }

    /**
     * Return the AI spending breakdown grouped by generation kind.
     *
     * @return JsonResponse A list of SummaryByActionTypeData wrapped in the API envelope.
     */
    public function summaryByActionType(): JsonResponse
    {
        return apiResponse($this->service->summaryByActionType());
    }

    /**
     * Return the monthly AI spending trend (padded to all twelve months).
     *
     * @return JsonResponse A list of month rows wrapped in the API envelope.
     */
    public function trend(): JsonResponse
    {
        return apiResponse($this->service->trend());
    }

    /**
     * Return the AI spending breakdown grouped by actor (name, role and totals).
     *
     * @return JsonResponse A list of SummaryByActorData wrapped in the API envelope.
     */
    public function summaryByActor(): JsonResponse
    {
        return apiResponse($this->service->summaryByActor());
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
}
