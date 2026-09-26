<?php

namespace Modules\Finance\Database\Seeders;

use App\Actions\Finance\Currency\ExchangeFetcher;
use Illuminate\Database\Seeder;
use Modules\Finance\Repository\CurrencyRepository;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $repo = app(CurrencyRepository::class);
        $currencies = ExchangeFetcher::run(fetchCurrenciesOnly: true);

        foreach ($currencies as $currency) {
            $data = $repo->show([
                'where' => ['code' => $currency],
            ]);

            if ($data) {
                // update
                $repo->update($data, [
                    'symbol' => $currency['symbol'],
                ]);
            } else {
                $isHaveBaseCurrency = false;
                if ($currency['currency'] == 'IDR') {
                    $isHaveBaseCurrency = true;
                }

                // Only 1 currency allowed to be the base currency. so update to false in seeder
                if ($isHaveBaseCurrency) {
                    // remove current base
                    $currentBaseCurrency = $repo->show([
                        'where' => [
                            'is_base' => true,
                        ]
                    ]);

                    if ($currentBaseCurrency) {
                        $repo->update($currentBaseCurrency, ['is_base' => false]);
                    }
                }

                $repo->store([
                    'is_base' => $isHaveBaseCurrency, // Auto true if that IDR
                    'is_active' => true,
                    'code' => $currency['currency'],
                    'name' => $currency['currency'],
                    'symbol' => $currency['symbol']
                ]);
            }
        }
    }
}
