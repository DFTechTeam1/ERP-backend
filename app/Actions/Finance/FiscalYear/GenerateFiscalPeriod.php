<?php

namespace App\Actions\Finance\FiscalYear;

use Lorisleiva\Actions\Concerns\AsAction;

class GenerateFiscalPeriod
{
    use AsAction;

    public function handle()
    {
        $config = [
            'year' => '2027',
            'period_type' => 'custom',
            'cycle_start_day' => 28,
            'start_posting_from' => null,
            'start_date' => '',
        ];
    }
}
