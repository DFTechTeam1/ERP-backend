<?php

use App\Actions\Finance\Currency\ExchangeFetcher;
use App\Enums\Finance\ExchangeRate\SourceRate;
use App\Exceptions\BaseCurrencyNotFound;
use App\Exceptions\ExchangeRateFetchFailed;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\Finance\Models\Currency;
use Modules\Finance\Models\ExchangeRate;
use Modules\Finance\Models\ExchangeRateApiLog;

use function Pest\Laravel\actingAs;

/**
 * ExchangeFetcher: pulls the latest rates (relative to the base currency) from the provider and
 * upserts them for today, one row per tracked currency per date, tagged with the `system` source.
 * A manual rate already recorded for the day is preserved (never overwritten).
 *
 * The HTTP call is always faked. `app.exchange_api_url` / `app.exchange_api_key` come from env (null
 * in testing), so they are set here to build a deterministic URL that Http::fake() matches with '*'.
 * The rate path records the acting user and writes an API log row, so an authenticated user is set
 * in beforeEach.
 *
 * `handle()` defaults to fetchCurrenciesOnly=true, so the rate path is exercised with
 * run(fetchCurrenciesOnly: false). The "one rate per date" guarantee relies on the
 * (currency_id, rate_date) unique index, so these tests require `make test-migrate`.
 */
beforeEach(function () {
    config([
        'app.exchange_api_url' => 'https://v6.exchangerate-api.com/v6',
        'app.exchange_api_key' => 'test-key',
    ]);

    actingAs(User::factory()->create());
});

/**
 * Fake the provider's /latest response.
 *
 * @param  array<string, float>  $conversionRates
 */
function fakeExchangeResponse(array $conversionRates, string $result = 'success'): void
{
    Http::fake([
        '*' => Http::response(array_filter([
            'result' => $result,
            'base_code' => 'IDR',
            'conversion_rates' => $result === 'success' ? $conversionRates : null,
            'error-type' => $result === 'success' ? null : 'quota-reached',
        ], fn ($value) => $value !== null), 200),
    ]);
}

/**
 * Fake the provider's /codes response (list of [code, name] pairs).
 *
 * @param  array<int, array{0: string, 1: string}>  $pairs
 */
function fakeCodesResponse(array $pairs, string $result = 'success'): void
{
    Http::fake([
        '*' => Http::response(array_filter([
            'result' => $result,
            'supported_codes' => $result === 'success' ? $pairs : null,
            'error-type' => $result === 'success' ? null : 'quota-reached',
        ], fn ($value) => $value !== null), 200),
    ]);
}

/** Create a currency in the master table. */
function makeCurrency(string $code, bool $isBase = false): Currency
{
    return Currency::create([
        'name' => $code.' Currency',
        'code' => $code,
        'symbol' => $code,
        'is_base' => $isBase,
        'is_active' => true,
    ]);
}

it('stores one system rate per tracked currency and skips unknown provider codes', function () {
    $idr = makeCurrency('IDR', isBase: true);
    $usd = makeCurrency('USD');
    $eur = makeCurrency('EUR');

    // XYZ is returned by the provider but not tracked locally -> must be ignored.
    fakeExchangeResponse(['USD' => 0.5, 'EUR' => 0.25, 'XYZ' => 1.23]);

    $codes = ExchangeFetcher::run(fetchCurrenciesOnly: false);

    $today = now()->toDateString();

    expect(ExchangeRate::count())->toBe(2)
        ->and((float) ExchangeRate::where('currency_id', $usd->id)->where('rate_date', $today)->value('rate'))->toBe(0.5)
        ->and((float) ExchangeRate::where('currency_id', $eur->id)->where('rate_date', $today)->value('rate'))->toBe(0.25)
        // rates fetched by the job are tagged as system-sourced and point back to the base currency
        ->and(ExchangeRate::where('currency_id', $usd->id)->value('source'))->toBe(SourceRate::System)
        ->and((int) ExchangeRate::where('currency_id', $usd->id)->value('parent_currency_id'))->toBe($idr->id)
        // upsert bypasses the model's creating hook, so the action must set uid itself
        ->and(ExchangeRate::whereNull('uid')->count())->toBe(0)
        // the return value exposes every provider code
        ->and($codes->all())->toContain('USD', 'EUR', 'XYZ')
        // the fetch is recorded as a successful API call
        ->and(ExchangeRateApiLog::where('is_success', true)->count())->toBe(1);
});

it('keeps a single rate per currency per date and refreshes a system rate on re-run', function () {
    makeCurrency('IDR', isBase: true);
    $usd = makeCurrency('USD');

    // Two sequential fetches. Http::fake() accumulates stubs (first match wins), so a sequence is
    // used to return a different payload on each call.
    Http::fake([
        '*' => Http::sequence()
            ->push(['result' => 'success', 'conversion_rates' => ['USD' => 0.5]], 200)
            ->push(['result' => 'success', 'conversion_rates' => ['USD' => 0.75]], 200),
    ]);

    ExchangeFetcher::run(fetchCurrenciesOnly: false);
    ExchangeFetcher::run(fetchCurrenciesOnly: false);

    $today = now()->toDateString();

    // still ONE row for the day, with the refreshed rate (no duplicate)
    expect(ExchangeRate::where('currency_id', $usd->id)->where('rate_date', $today)->count())->toBe(1)
        ->and((float) ExchangeRate::where('currency_id', $usd->id)->where('rate_date', $today)->value('rate'))->toBe(0.75);
});

it('never overwrites a manually-set rate; only system and brand-new rates are written', function () {
    $idr = makeCurrency('IDR', isBase: true);
    $usd = makeCurrency('USD');
    $eur = makeCurrency('EUR');
    $jpy = makeCurrency('JPY');
    $today = now()->toDateString();

    // USD: a MANUAL rate already set for today. EUR: a SYSTEM rate already set. JPY: no rate yet.
    ExchangeRate::create([
        'currency_id' => $usd->id,
        'parent_currency_id' => $idr->id,
        'rate_date' => $today,
        'rate' => 0.9,
        'source' => SourceRate::Manual,
    ]);
    ExchangeRate::create([
        'currency_id' => $eur->id,
        'parent_currency_id' => $idr->id,
        'rate_date' => $today,
        'rate' => 0.1,
        'source' => SourceRate::System,
    ]);

    fakeExchangeResponse(['USD' => 0.5, 'EUR' => 0.25, 'JPY' => 0.01]);

    ExchangeFetcher::run(fetchCurrenciesOnly: false);

    expect((float) ExchangeRate::where('currency_id', $usd->id)->where('rate_date', $today)->value('rate'))->toBe(0.9) // manual kept
        ->and(ExchangeRate::where('currency_id', $usd->id)->where('rate_date', $today)->value('source'))->toBe(SourceRate::Manual)
        ->and((float) ExchangeRate::where('currency_id', $eur->id)->where('rate_date', $today)->value('rate'))->toBe(0.25) // system refreshed
        ->and((float) ExchangeRate::where('currency_id', $jpy->id)->where('rate_date', $today)->value('rate'))->toBe(0.01) // new inserted
        ->and(ExchangeRate::where('currency_id', $jpy->id)->where('rate_date', $today)->value('source'))->toBe(SourceRate::System)
        ->and(ExchangeRate::where('rate_date', $today)->count())->toBe(3);
});

it('issues a constant, small number of queries regardless of provider currency count (no N+1)', function () {
    makeCurrency('IDR', isBase: true);
    makeCurrency('USD');
    makeCurrency('EUR');

    // 50 provider codes, only 2 of which are tracked.
    $rates = [];
    foreach (range(1, 50) as $i) {
        $rates['C'.$i] = $i / 100;
    }
    $rates['USD'] = 0.5;
    $rates['EUR'] = 0.25;
    fakeExchangeResponse($rates);

    DB::enableQueryLog();
    ExchangeFetcher::run(fetchCurrenciesOnly: false);
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // base lookup, actor lookup, api-log insert, currencies preload, existing-rates preload, upsert
    // => a small constant, nowhere near one query per provider code
    expect($queryCount)->toBeLessThanOrEqual(8);
});

it('returns the supported currencies as a code => name map without writing rates (fetchCurrenciesOnly)', function () {
    // No base currency needed: this path hits /codes and returns before any rate work.
    fakeCodesResponse([['USD', 'United States Dollar'], ['EUR', 'Euro']]);

    $currencies = ExchangeFetcher::run(fetchCurrenciesOnly: true);

    expect($currencies->toArray())->toBe(['USD' => 'United States Dollar', 'EUR' => 'Euro'])
        ->and(ExchangeRate::count())->toBe(0);
});

it('throws when no base currency is configured', function () {
    fakeExchangeResponse(['USD' => 0.5]);

    ExchangeFetcher::run(fetchCurrenciesOnly: false);
})->throws(BaseCurrencyNotFound::class);

it('throws when the provider returns an application-level error', function () {
    makeCurrency('IDR', isBase: true);
    fakeExchangeResponse([], result: 'error');

    ExchangeFetcher::run(fetchCurrenciesOnly: false);
})->throws(ExchangeRateFetchFailed::class);
