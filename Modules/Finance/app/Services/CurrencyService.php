<?php

namespace Modules\Finance\Services;

use App\Actions\Finance\Currency\ExchangeFetcher;
use App\Data\Finance\Currency\AddRateData;
use App\Data\Finance\Currency\ListCurrencyData;
use App\Data\Finance\Currency\StoreCurrencyData;
use App\Data\Finance\Currency\UpdateCurrencyData;
use App\Data\Finance\ExchangeRate\ListExchangeRateData;
use App\Enums\Finance\ExchangeRate\SourceRate;
use App\Exceptions\DataNotFound;
use Carbon\Carbon;
use Google\Service\SecurityCommandCenter\EffectiveEventThreatDetectionCustomModule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\Currency;
use Modules\Finance\Repository\CurrencyRepository;
use Modules\Finance\Repository\ExchangeRateRepository;

class CurrencyService
{
    const DEFAULT_BASE_CURRENCY = 'IDR';

    public function __construct(
        private readonly CurrencyRepository $repo,
        private readonly ExchangeRateRepository $exchangeRepo
    ) {}

    public function syncCurrencies()
    {
        DB::beginTransaction();
        try {
            $currencies = ExchangeFetcher::run(fetchCurrenciesOnly: true);

            foreach ($currencies as $currency) {
            }

            DB::commit();

            return generalResponse(
                message: "Currencies updated"
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    public function store(StoreCurrencyData $payload): array
    {
        DB::beginTransaction();
        try {
            $currency = $this->repo->store([
                'name' => $payload->name,
                'code' => $payload->code,
                'symbol' => $payload->symbol,
                'is_base' => false
            ]);

            $openingRate = floatval($payload->opening_rate);
            if ($openingRate > 0) {
                $currency->rates()->create([
                    'rate' => $openingRate,
                    'rate_date' => Carbon::today()->format('Y-m-d'),
                    'source' => SourceRate::Manual,
                    'created_by' => Auth::id()
                ]);
            }

            DB::commit();

            return generalResponse(
                message: "Currency created"
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    public function list(): array
    {
        try {
            $active = request('active');
            $search = request('search');

            $whereLike = [];

            if ($search) {
                $whereLike['name'] = "%{$search}%";
                $whereLike['code'] = "%{$search}%";
            }

            $currencies = $this->repo->get([
                'with' => [
                    'latestRates'
                ],
                'whereLike' => $whereLike,
                'whereNotNull' => [
                    'symbol'
                ]
            ]);

            /** @var array<int, ListCurrencyData> */
            $output = [];

            foreach ($currencies as $currency) {
                $lastRates = $currency->latestRates;
                $rate = $lastRates->count() ? floatval($lastRates?->first()?->rate ?? 0) : 0;
                $rateAsOf = $lastRates->count() ? $lastRates->first()->rate_date : null;
                $firstRate = $lastRates->count() == 2 ? floatval($lastRates?->last()?->rate ?? 0) : 0;
                $rateChange = $lastRates->count() == 2 ? $rate - $firstRate : 0;

                $output[] = new ListCurrencyData(
                    uid: $currency->uid,
                    name: $currency->name,
                    symbol: $currency->symbol,
                    code: $currency->code,
                    isBase: $currency->is_base,
                    isActive: $currency->is_active,
                    currentRate: $rate,
                    rateChange: round($rateChange, 2),
                    rateAsOf: $rateAsOf
                );
            }

            return generalResponse(
                message: 'Success',
                data: $output
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    protected function show(string $currencyUid): ?Currency
    {
        $currency = $this->repo->showByUid($currencyUid);

        if (! $currency) {
            throw new DataNotFound('Currency not found', 404);
        }

        return $currency;
    }

    public function update(UpdateCurrencyData $payload, string $currencyUid): array
    {
        try {
            $this->repo->update($this->show($currencyUid), $payload->toArray());

            return generalResponse(
                message: "Currency updated"
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function historyRates(string $currencyUid): array
    {
        try {
            $page = request('page');
            $limit = request('limit');
            $sortBy = request('sortBy');

            $currency = $this->repo->showByUid($currencyUid);
            $orderBy = [];

            if ($sortBy && count($sortBy) > 0) {
                foreach ($sortBy as $sort) {
                    $orderBy[$sort['key']] = $sort['order'];
                }
            } else {
                $orderBy['created_at'] = 'desc';
            }

            $rates = $this->exchangeRepo->paginate(
                params: [
                    'where' => [
                        'currency_id' => $currency->id,
                    ],
                    'with' => [
                        'creator:id,employee_id',
                        'creator.employee:id,uid,name'
                    ],
                    'orderBy' => $orderBy
                ],
                perPage: $limit
            );
            $totalData = $this->exchangeRepo->get([
                'where' => [
                    'currency_id' => $currency->id,
                ],
                'select' => ['id']
            ])->count();

            /** @var array<int, ListExchangeRateData> */
            $output = [];

            foreach ($rates->items() as $rate) {
                $output[] = new ListExchangeRateData(
                    uid: $rate->uid,
                    currencyUid: $currencyUid,
                    effectiveDate: $rate->rate_date,
                    rate: floatval($rate->rate),
                    source: $rate->source->value,
                    setBy: $rate?->creator?->employee?->name ?? '-',
                    setByUid: $rate?->creator?->employee?->uid ?? '-',
                    createdAt: $rate->created_at,
                    updatedAt: $rate?->updated_at ?? '',
                );
            }

            return generalResponse(
                message: "Success",
                data: [
                    'paginated' => $output,
                    'totalData' => $totalData
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function addRate(AddRateData $payload, string $currencyUid): array
    {
        try {
            $currency = $this->repo->showByUid($currencyUid);

            if (! $currency) {
                throw new DataNotFound('Currency is not found', 404);
            }

            $currency->rates()->create([
                'rate_date' => $payload->effective_date,
                'rate' => $payload->rate,
                'source' => SourceRate::Manual,
                'created_by' => Auth::id()
            ]);

            return generalResponse(
                message: "New rate has beed added"
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function updateRate(AddRateData $payload, string $currencyUid, string $rateUid): array
    {
        try {
            $currency = $this->repo->showByUid($currencyUid);

            if (! $currency) {
                throw new DataNotFound('Currency not found', 404);
            }

            $rate = $this->exchangeRepo->show([
                'where' => [
                    'uid' => $rateUid
                ]
            ]);
            if ($rate) {
                $this->exchangeRepo->update($rate, [
                    'rate' => $payload->rate,
                    'source' => SourceRate::Manual
                ]);
            }

            return generalResponse(
                message: "Rate has been updated"
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}
