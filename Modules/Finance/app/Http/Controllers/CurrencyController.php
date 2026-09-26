<?php

namespace Modules\Finance\Http\Controllers;

use App\Data\Finance\Currency\AddRateData;
use App\Data\Finance\Currency\StoreCurrencyData;
use App\Data\Finance\Currency\UpdateCurrencyData;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Finance\Services\CurrencyService;

class CurrencyController extends Controller
{
    public function __construct(
        private readonly CurrencyService $service
    ) {}
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        return apiResponse($this->service->list());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCurrencyData $request): JsonResponse
    {
        return apiResponse($this->service->store($request));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCurrencyData $request, string $currencyUid): JsonResponse
    {
        return apiResponse($this->service->update($request, $currencyUid));
    }

    public function historyRates(string $currencyUid): JsonResponse
    {
        return apiResponse($this->service->historyRates($currencyUid));
    }

    public function addRate(AddRateData $request, string $currencyUid): JsonResponse
    {
        return apiResponse($this->service->addRate($request, $currencyUid));
    }

    public function updateRate(AddRateData $request, string $currencyUid, string $rateUid): JsonResponse
    {
        return apiResponse($this->service->updateRate($request, $currencyUid, $rateUid));
    }
}
