<?php

namespace App\Actions\Finance\Currency;

use App\Enums\Finance\ExchangeRate\SourceRate;
use App\Exceptions\BaseCurrencyNotFound;
use App\Exceptions\ExchangeRateFetchFailed;
use App\Repository\UserRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Finance\Repository\CurrencyRepository;
use Modules\Finance\Repository\ExchangeRateApiLogRepository;
use Modules\Finance\Repository\ExchangeRateRepository;

class ExchangeFetcher
{
    use AsAction;

    /**
     * Fetch the latest exchange rates (relative to the base currency) from the provider and store
     * them for today, one row per tracked currency per date.
     *
     * The provider returns ~160 currencies; only currencies that exist in the local master table are
     * persisted. Rates are written with a single upsert keyed on (currency_id, rate_date), so a given
     * currency never has more than one rate for a given date and re-running the job just refreshes it.
     * Rates written by this job are tagged with the `system` source.
     *
     * @param  bool  $fetchCurrenciesOnly  When true, hit the provider's /codes endpoint and return the
     *                                     supported currencies as a `code => name` map without writing
     *                                     any rates (used to sync the currency master table).
     * @return Collection<string, string>|Collection<int, string> A `code => name` map when
     *                                                            $fetchCurrenciesOnly is true, otherwise the list of processed currency codes.
     *
     * @throws BaseCurrencyNotFound When no base currency is configured.
     * @throws ExchangeRateFetchFailed When the provider responds with an application-level error.
     */
    public function handle(bool $fetchCurrenciesOnly = true): Collection
    {
        if ($fetchCurrenciesOnly) {
            return $this->fetchSupportedCurrencies();
        }

        $currencyRepo = app(CurrencyRepository::class);
        $exchangeRepo = app(ExchangeRateRepository::class);
        $logRepo = app(ExchangeRateApiLogRepository::class);
        $userRepo = app(UserRepository::class);

        $baseCurrency = $currencyRepo->show([
            'where' => ['is_base' => 1],
            'select' => ['code', 'id'],
        ]);

        if (! $baseCurrency) {
            throw new BaseCurrencyNotFound;
        }

        $actor = $userRepo->detail(id: Auth::id(), select: 'id,email,employee_id', relation: ['employee:id,name']);

        $fetchUrl = config('app.exchange_api_url').'/'.config('app.exchange_api_key')."/latest/{$baseCurrency->code}";
        $res = Http::get($fetchUrl)
            ->throw()
            ->json();

        $isFailed = ($res['result'] ?? null) !== 'success' || ! isset($res['conversion_rates']);

        $logRepo->store([
            'url' => $fetchUrl,
            'response' => json_encode($res),
            'is_success' => $isFailed ? false : true,
            'actor_name' => $actor ? ($actor->employee ? $actor->employee->name : $actor->email) : '-',
            'response_code' => null,
        ]);

        // ->throw() only catches HTTP 4xx/5xx; the provider also signals quota/key errors with a 200
        // body of {"result":"error", ...}, so the payload itself must be validated before use.
        if ($isFailed) {
            throw new ExchangeRateFetchFailed($res['error-type'] ?? 'unknown');
        }

        $conversionRates = $res['conversion_rates'];

        // Preload the tracked currencies once (code => id) to avoid an N+1 lookup per provider rate.
        $currencies = $currencyRepo->get(['select' => ['id', 'code']])->keyBy('code');

        $today = now()->toDateString();

        // Preload today's rates for this base currency (currency_id => rate) so manual rates are
        // preserved: a currency whose rate for today was set manually is skipped entirely (never
        // overwritten); only system rates are refreshed and currencies with no rate yet are inserted.
        $existingRates = $exchangeRepo->get([
            'where' => [
                'rate_date' => $today,
                'parent_currency_id' => $baseCurrency->id,
            ],
            'select' => ['currency_id', 'source'],
        ])->keyBy('currency_id');

        $rows = [];
        foreach ($conversionRates as $code => $rate) {
            $currency = $currencies->get($code);

            if (! $currency) {
                continue;
            }

            $existing = $existingRates->get($currency->id);
            // Only (re)write system-sourced or brand-new rates; never touch a manual one.
            if ($existing && $existing->source !== SourceRate::System) {
                continue;
            }

            $rows[] = [
                'uid' => Str::uuid()->toString(),
                'currency_id' => $currency->id,
                'parent_currency_id' => $baseCurrency->id,
                'rate_date' => $today,
                'rate' => $rate,
                'source' => SourceRate::System->value,
                'created_by' => Auth::id(),
            ];
        }

        if ($rows !== []) {
            // Single statement; the (currency_id, rate_date) unique index guarantees one rate per day.
            // Manual rates were excluded above, so a conflict only refreshes a previous system rate.
            $exchangeRepo->upsert($rows, ['currency_id', 'rate_date', 'parent_currency_id'], ['rate']);
        }

        return collect(array_keys($conversionRates));
    }

    /**
     * Fetch the provider's full list of supported currencies from the /codes endpoint.
     *
     * @return Collection<string, string> A `code => name` map, e.g. ['USD' => 'United States Dollar'].
     *
     * @throws ExchangeRateFetchFailed When the provider responds with an application-level error.
     */
    protected function fetchSupportedCurrencies(): Collection
    {
        $res = Http::get(config('app.exchange_api_url').'/'.config('app.exchange_api_key').'/codes')
            ->throw()
            ->json();

        if (($res['result'] ?? null) !== 'success' || ! isset($res['supported_codes'])) {
            throw new ExchangeRateFetchFailed($res['error-type'] ?? 'unknown');
        }

        // supported_codes is a list of [code, name] pairs: [["USD", "United States Dollar"], ...]
        return collect($res['supported_codes'])->mapWithKeys(
            fn (array $pair): array => [$pair[0] => $pair[1]]
        );
    }
}
