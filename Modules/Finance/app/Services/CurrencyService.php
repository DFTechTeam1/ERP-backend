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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Finance\Models\Currency;
use Modules\Finance\Repository\CurrencyRepository;
use Modules\Finance\Repository\ExchangeRateRepository;

class CurrencyService
{
    const DEFAULT_BASE_CURRENCY = 'IDR';

    /**
     * @param  CurrencyRepository  $repo  Repository for the currency master table.
     * @param  ExchangeRateRepository  $exchangeRepo  Repository for per-currency exchange rates.
     */
    public function __construct(
        private readonly CurrencyRepository $repo,
        private readonly ExchangeRateRepository $exchangeRepo
    ) {}

    /**
     * Sync the currency master table with the provider's supported currencies: any code the provider
     * lists that is not already stored is inserted (with its name; symbol is left null so it stays out
     * of the list() UI until an admin sets one). Existing currencies are left untouched.
     *
     * @return array{error: bool, message: string, data?: array<string, mixed>, code?: int} The
     *                                                                                      standard API response envelope; `data.added` is the number of currencies inserted.
     */
    public function syncCurrencies(bool $fetchCurrencyOnly = true): array
    {
        DB::beginTransaction();
        try {
            /** @var Collection<string, string> $available */
            $available = ExchangeFetcher::run(fetchCurrenciesOnly: $fetchCurrencyOnly);

            $existingCodes = $this->repo->get(['select' => ['code']])->pluck('code')->flip();

            $now = now();
            $rows = [];
            foreach ($available as $code => $name) {
                if ($existingCodes->has($code)) {
                    continue;
                }

                $rows[] = [
                    'uid' => Str::uuid()->toString(),
                    'name' => $name,
                    'code' => $code,
                    'is_base' => false,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($rows !== []) {
                $this->repo->insert($rows);
            }

            DB::commit();

            return generalResponse(
                message: 'Currencies updated',
                data: ['added' => count($rows)]
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Create a new (non-base) currency and, when an opening rate is supplied, record it as today's
     * manual rate attributed to the acting user. Runs in a transaction.
     *
     * @param  StoreCurrencyData  $payload  The validated currency payload (code, name, symbol, opening_rate).
     * @return array{error: bool, message: string, data?: array<string, mixed>, code?: int} The
     *                                                                                      standard API response envelope.
     */
    public function store(StoreCurrencyData $payload): array
    {
        DB::beginTransaction();
        try {
            $currency = $this->repo->store([
                'name' => $payload->name,
                'code' => $payload->code,
                'symbol' => $payload->symbol,
                'is_base' => false,
            ]);

            $openingRate = floatval($payload->opening_rate);
            if ($openingRate > 0) {
                $currency->rates()->create([
                    'rate' => $openingRate,
                    'rate_date' => Carbon::today()->format('Y-m-d'),
                    'source' => SourceRate::Manual,
                    'created_by' => Auth::id(),
                ]);
            }

            DB::commit();

            return generalResponse(
                message: 'Currency created'
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * List currencies that have a symbol set, each with its latest rate and the change since the
     * previous rate. Honours the `search` request filter (matched against name and code).
     *
     * @return array{error: bool, message: string, data?: array<int, ListCurrencyData>, code?: int} The
     *                                                                                              standard API response envelope carrying the formatted currency rows.
     */
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
                    'latestRates',
                ],
                'whereLike' => $whereLike,
                'whereNotNull' => [
                    'symbol',
                ],
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

    /**
     * Resolve a currency by its uid, or fail.
     *
     * @param  string  $currencyUid  The currency uid.
     * @return Currency The matching currency (never null; throws instead).
     *
     * @throws DataNotFound When no currency matches the uid.
     */
    protected function show(string $currencyUid): ?Currency
    {
        $currency = $this->repo->showByUid($currencyUid);

        if (! $currency) {
            throw new DataNotFound('Currency not found', 404);
        }

        return $currency;
    }

    /**
     * Update an existing currency's editable fields.
     *
     * @param  UpdateCurrencyData  $payload  The validated update payload.
     * @param  string  $currencyUid  The uid of the currency to update.
     * @return array{error: bool, message: string, data?: array<string, mixed>, code?: int} The
     *                                                                                      standard API response envelope.
     */
    public function update(UpdateCurrencyData $payload, string $currencyUid): array
    {
        try {
            $this->repo->update($this->show($currencyUid), $payload->toArray());

            return generalResponse(
                message: 'Currency updated'
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Build the paginated rate history for a currency, newest first by default. Honours the `page`,
     * `limit` and `sortBy` request parameters, and includes each rate's source and the employee who
     * set it.
     *
     * @param  string  $currencyUid  The uid of the currency whose rates are listed.
     * @return array{error: bool, message: string, data?: array{paginated: array<int, ListExchangeRateData>, totalData: int}, code?: int}
     *                                                                                                                                    The standard API response envelope carrying the paginated rate rows and the total count.
     */
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
                        'creator.employee:id,uid,name',
                    ],
                    'orderBy' => $orderBy,
                ],
                perPage: $limit
            );
            $totalData = $this->exchangeRepo->get([
                'where' => [
                    'currency_id' => $currency->id,
                ],
                'select' => ['id'],
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
                message: 'Success',
                data: [
                    'paginated' => $output,
                    'totalData' => $totalData,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Add a manual exchange rate to a currency, attributed to the acting user.
     *
     * @param  AddRateData  $payload  The rate payload (effective date and rate value).
     * @param  string  $currencyUid  The uid of the currency to add the rate to.
     * @return array{error: bool, message: string, data?: array<string, mixed>, code?: int} The
     *                                                                                      standard API response envelope.
     *
     * @throws DataNotFound Caught internally and returned as an error envelope when the currency is missing.
     */
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
                'created_by' => Auth::id(),
            ]);

            return generalResponse(
                message: 'New rate has beed added'
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Update an existing exchange rate's value, re-tagging it as a manual entry. The payload's
     * effective date is not changed.
     *
     * @param  AddRateData  $payload  The rate payload (the new rate value is used).
     * @param  string  $currencyUid  The uid of the owning currency.
     * @param  string  $rateUid  The uid of the rate to update.
     * @return array{error: bool, message: string, data?: array<string, mixed>, code?: int} The
     *                                                                                      standard API response envelope.
     *
     * @throws DataNotFound Caught internally and returned as an error envelope when the currency is missing.
     */
    public function updateRate(AddRateData $payload, string $currencyUid, string $rateUid): array
    {
        try {
            $currency = $this->repo->showByUid($currencyUid);

            if (! $currency) {
                throw new DataNotFound('Currency not found', 404);
            }

            $rate = $this->exchangeRepo->show([
                'where' => [
                    'uid' => $rateUid,
                ],
            ]);
            if ($rate) {
                $this->exchangeRepo->update($rate, [
                    'rate' => $payload->rate,
                    'source' => SourceRate::Manual,
                ]);
            }

            return generalResponse(
                message: 'Rate has been updated'
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}
