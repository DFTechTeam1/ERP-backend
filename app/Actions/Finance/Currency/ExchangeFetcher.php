<?php

namespace App\Actions\Finance\Currency;

use Blvckgvd\Currencylib\CurrencyLib;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class ExchangeFetcher
{
    use AsAction;

    public function handle(bool $fetchCurrenciesOnly = false): array
    {
        $response = Http::get(config('app.exchange_api_url').'/'.config('app.exchange_api_key').'/latest/IDR');

        if ($response->successful()) {
            $res = $response->json();
            $latestUpdateDate = Carbon::parse($res['time_last_update_utc'])->timezone('Asia/Jakarta')->format('Y-m-d');
            $rates = $res['conversion_rates'];

            $currencies = array_keys($rates);

            $currencyFormat = [];
            foreach ($currencies as $currency) {
                // Get the symbol of each currency
                $symbol = CurrencyLib::getCurrencySymbol($currency);
                $currencyFormat[] = [
                    'symbol' => $symbol,
                    'currency' => $currency,
                    'name' => $currency,
                ];
            }

            if ($fetchCurrenciesOnly) {
                return $currencyFormat;
            }

            return [];
        }

        return [];
    }
}
