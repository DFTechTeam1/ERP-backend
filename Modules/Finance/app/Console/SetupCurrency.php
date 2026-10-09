<?php

namespace Modules\Finance\Console;

use App\Actions\Finance\Currency\ExchangeFetcher;
use Blvckgvd\Currencylib\CurrencyLib;
use Illuminate\Console\Command;
use Modules\Finance\Models\Currency;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class SetupCurrency extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'finance:setup-currencies';

    /**
     * The console command description.
     */
    protected $description = 'Fetch all available currencies and insert as record';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $currencies = ExchangeFetcher::run(true);

        $payload = [];
        $a = 0;
        foreach ($currencies as $code => $name) {
            $payload[] = [
                'name' => $name,
                'symbol' => CurrencyLib::getCurrencySymbol($code),
                'code' => $code,
                'is_base' => $code == 'IDR' ? true : false,
                'is_active' => true,
                'uid' => Uuid::uuid4()->toString()
            ];
            $a++;
        }

        Currency::upsert(
            $payload,
            ['code'],
            ['name', 'symbol', 'is_base', 'is_active', 'uid']
        );
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return [
            ['example', InputArgument::REQUIRED, 'An example argument.'],
        ];
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['example', null, InputOption::VALUE_OPTIONAL, 'An example option.', null],
        ];
    }
}
