<?php

use App\Data\Finance\Currency\StoreCurrencyData;
use App\Enums\Finance\ExchangeRate\SourceRate;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\Finance\Models\Currency;
use Modules\Finance\Models\ExchangeRate;
use Modules\Finance\Services\CurrencyService;
use Modules\Hrd\Models\Employee;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

/**
 * CurrencyService.
 *
 * - syncCurrencies() pulls the provider's supported currencies (code => name, via ExchangeFetcher's
 *   /codes path) and inserts the ones missing from the master table, leaving existing ones alone.
 * - fetchRates() fetches and stores today's rates (via ExchangeFetcher's /latest path).
 * - store() / addRate() record a rate's source (manual), author (created_by) and base as parent.
 * - historyRates() reports each rate's source and the employee who set it.
 */
function currencyService(): CurrencyService
{
    return app(CurrencyService::class);
}

beforeEach(function () {
    config([
        'app.exchange_api_url' => 'https://v6.exchangerate-api.com/v6',
        'app.exchange_api_key' => 'test-key',
    ]);
});

it('inserts provider currencies missing from the master table and skips existing ones', function () {
    Currency::create(['name' => 'Indonesian Rupiah', 'code' => 'IDR', 'is_base' => true, 'is_active' => true]);
    Currency::create(['name' => 'US Dollar', 'code' => 'USD', 'is_active' => true]);

    Http::fake(['*' => Http::response([
        'result' => 'success',
        'supported_codes' => [
            ['IDR', 'Indonesian Rupiah'],
            ['USD', 'US Dollar'],
            ['EUR', 'Euro'],
            ['JPY', 'Japanese Yen'],
        ],
    ], 200)]);

    $response = currencyService()->syncCurrencies();

    expect($response['error'])->toBeFalse()
        ->and($response['data']['added'])->toBe(2);

    // the two new currencies are created with their names; the existing two are not duplicated
    expect(Currency::count())->toBe(4)
        ->and(Currency::where('code', 'EUR')->value('name'))->toBe('Euro')
        ->and(Currency::where('code', 'JPY')->value('name'))->toBe('Japanese Yen')
        ->and(Currency::where('code', 'USD')->count())->toBe(1)
        ->and(Currency::where('code', 'IDR')->count())->toBe(1);
});

it('is a no-op on a second run when the master already has every provider currency', function () {
    Currency::create(['name' => 'Indonesian Rupiah', 'code' => 'IDR', 'is_base' => true, 'is_active' => true]);

    Http::fake(['*' => Http::response([
        'result' => 'success',
        'supported_codes' => [['IDR', 'Indonesian Rupiah'], ['USD', 'US Dollar']],
    ], 200)]);

    currencyService()->syncCurrencies();      // adds USD
    $second = currencyService()->syncCurrencies(); // nothing left to add

    expect($second['data']['added'])->toBe(0)
        ->and(Currency::count())->toBe(2);
});

it('store() records a manual opening rate with its source, author and base as parent', function () {
    $user = User::factory()->create();
    actingAs($user);
    $idr = Currency::create(['name' => 'Indonesian Rupiah', 'code' => 'IDR', 'is_base' => true, 'is_active' => true]);

    $response = currencyService()->store(new StoreCurrencyData(
        code: 'GBP',
        name: 'Pound Sterling',
        symbol: '£',
        rate: 20000,
    ));

    expect($response['error'])->toBeFalse();

    $currency = Currency::where('code', 'GBP')->first();

    assertDatabaseHas('exchange_rates', [
        'currency_id' => $currency->id,
        'parent_currency_id' => $idr->id,
        'rate' => 20000,
        'source' => SourceRate::Manual->value,
        'created_by' => $user->id,
    ]);

    // the enum cast round-trips on read
    expect(ExchangeRate::where('currency_id', $currency->id)->first()->source)->toBe(SourceRate::Manual);
});

it('historyRates() returns each rate with its source and the employee who set it', function () {
    $employee = Employee::factory()->withUser()->create(['name' => 'Rina']);
    $user = User::where('employee_id', $employee->id)->firstOrFail();
    actingAs($user);

    $idr = Currency::create(['name' => 'Indonesian Rupiah', 'code' => 'IDR', 'is_base' => true, 'is_active' => true]);
    $currency = Currency::create(['name' => 'Euro', 'code' => 'EUR', 'symbol' => '€', 'is_active' => true]);
    $currency->rates()->create([
        'parent_currency_id' => $idr->id,
        'rate' => 16000,
        'rate_date' => now()->toDateString(),
        'source' => SourceRate::Manual,
        'created_by' => $user->id,
    ]);

    request()->merge(['limit' => 10]);

    $response = currencyService()->historyRates($currency->uid);

    expect($response['error'])->toBeFalse();

    $rows = $response['data']['paginated'];
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->source)->toBe('manual')
        ->and($rows[0]->by)->toBe('Rina')
        ->and($rows[0]->rate)->toBe(16000.0);
});

it('fetchRates() fetches and stores todays system rates and reports success', function () {
    actingAs(User::factory()->create());
    $idr = Currency::create(['name' => 'Indonesian Rupiah', 'code' => 'IDR', 'is_base' => true, 'is_active' => true]);
    $usd = Currency::create(['name' => 'US Dollar', 'code' => 'USD', 'is_active' => true]);

    Http::fake(['*' => Http::response([
        'result' => 'success',
        'conversion_rates' => ['USD' => 0.5, 'EUR' => 0.25],
    ], 200)]);

    $response = currencyService()->fetchRates();

    expect($response['error'])->toBeFalse()
        ->and($response['message'])->toBe('Rates updated')
        ->and((float) ExchangeRate::where('currency_id', $usd->id)->value('rate'))->toBe(0.5)
        ->and(ExchangeRate::where('currency_id', $usd->id)->value('source'))->toBe(SourceRate::System)
        ->and((int) ExchangeRate::where('currency_id', $usd->id)->value('parent_currency_id'))->toBe($idr->id);
});

it('fetchRates() returns an error envelope when no base currency is configured', function () {
    actingAs(User::factory()->create());

    Http::fake(['*' => Http::response(['result' => 'success', 'conversion_rates' => ['USD' => 0.5]], 200)]);

    // ExchangeFetcher throws BaseCurrencyNotFound, which fetchRates catches and returns as an error.
    $response = currencyService()->fetchRates();

    expect($response['error'])->toBeTrue();
});
