<?php

namespace App\Actions\Finance\Currency;

use App\Enums\Finance\ExchangeRate\SourceRate;
use App\Exceptions\BaseCurrencyNotFound;
use App\Exceptions\ExchangeRateFetchFailed;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Finance\Repository\CurrencyRepository;
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
    public function handle(bool $fetchCurrenciesOnly = false): Collection
    {
        if ($fetchCurrenciesOnly) {
            return $this->fetchSupportedCurrencies();
        }

        $currencyRepo = app(CurrencyRepository::class);
        $exchangeRepo = app(ExchangeRateRepository::class);

        $baseCurrency = $currencyRepo->show([
            'where' => ['is_base' => 1],
            'select' => ['code'],
        ]);

        if (! $baseCurrency) {
            throw new BaseCurrencyNotFound;
        }

        $res = Http::get(config('app.exchange_api_url').'/'.config('app.exchange_api_key')."/latest/{$baseCurrency->code}")
            ->throw()
            ->json();

        // ->throw() only catches HTTP 4xx/5xx; the provider also signals quota/key errors with a 200
        // body of {"result":"error", ...}, so the payload itself must be validated before use.
        if (($res['result'] ?? null) !== 'success' || ! isset($res['conversion_rates'])) {
            throw new ExchangeRateFetchFailed($res['error-type'] ?? 'unknown');
        }

        $conversionRates = $res['conversion_rates'];

        // Preload the tracked currencies once (code => id) to avoid an N+1 lookup per provider rate.
        $currencies = $currencyRepo->get(['select' => ['id', 'code']])->keyBy('code');

        $today = now()->toDateString();

        $rows = [];
        foreach ($conversionRates as $code => $rate) {
            $currency = $currencies->get($code);

            if (! $currency) {
                continue;
            }

            $rows[] = [
                'uid' => Str::uuid()->toString(),
                'currency_id' => $currency->id,
                'rate_date' => $today,
                'rate' => $rate,
                'source' => SourceRate::System->value,
            ];
        }

        if ($rows !== []) {
            // Single statement; the (currency_id, rate_date) unique index guarantees one rate per day.
            // Only `rate` is refreshed on conflict, so a manual rate for the day keeps its attribution.
            $exchangeRepo->upsert($rows, ['currency_id', 'rate_date'], ['rate']);
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
