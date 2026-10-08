<?php

namespace Modules\Finance\Http\Controllers;

use App\Data\Finance\Currency\AddRateData;
use App\Data\Finance\Currency\StoreCurrencyData;
use App\Data\Finance\Currency\UpdateCurrencyData;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Finance\Services\CurrencyService;

class CurrencyController extends Controller
{
    /**
     * @param  CurrencyService  $service  Service backing the currency endpoints.
     */
    public function __construct(
        private readonly CurrencyService $service
    ) {}

    /**
     * List currencies with their latest rate.
     *
     * @return JsonResponse A list of ListCurrencyData wrapped in the API envelope.
     */
    public function index(): JsonResponse
    {
        return apiResponse($this->service->list());
    }

    /**
     * Create a new currency, optionally with an opening rate.
     *
     * @param  StoreCurrencyData  $request  The validated currency payload.
     * @return JsonResponse The API response envelope.
     */
    public function store(StoreCurrencyData $request): JsonResponse
    {
        return apiResponse($this->service->store($request));
    }

    /**
     * Update an existing currency.
     *
     * @param  UpdateCurrencyData  $request  The validated update payload.
     * @param  string  $currencyUid  The uid of the currency to update.
     * @return JsonResponse The API response envelope.
     */
    public function update(UpdateCurrencyData $request, string $currencyUid): JsonResponse
    {
        return apiResponse($this->service->update($request, $currencyUid));
    }

    /**
     * Return the paginated rate history for a currency.
     *
     * @param  string  $currencyUid  The uid of the currency.
     * @return JsonResponse A paginated list of ListExchangeRateData wrapped in the API envelope.
     */
    public function historyRates(string $currencyUid): JsonResponse
    {
        return apiResponse($this->service->historyRates($currencyUid));
    }

    /**
     * Add a manual exchange rate to a currency.
     *
     * @param  AddRateData  $request  The rate payload (effective date and rate value).
     * @param  string  $currencyUid  The uid of the currency.
     * @return JsonResponse The API response envelope.
     */
    public function addRate(AddRateData $request, string $currencyUid): JsonResponse
    {
        return apiResponse($this->service->addRate($request, $currencyUid));
    }

    /**
     * Update an existing exchange rate of a currency.
     *
     * @param  AddRateData  $request  The rate payload (the new rate value is used).
     * @param  string  $currencyUid  The uid of the owning currency.
     * @param  string  $rateUid  The uid of the rate to update.
     * @return JsonResponse The API response envelope.
     */
    public function updateRate(AddRateData $request, string $currencyUid, string $rateUid): JsonResponse
    {
        return apiResponse($this->service->updateRate($request, $currencyUid, $rateUid));
    }
}
