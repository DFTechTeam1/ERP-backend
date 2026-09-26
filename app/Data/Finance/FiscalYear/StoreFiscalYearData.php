<?php

namespace App\Data\Finance\FiscalYear;

use App\Enums\Finance\FiscalYear\PeriodType;
use Spatie\LaravelData\Data;

class StoreFiscalYearData extends Data
{
    public function __construct(
        public readonly string $year,
        public readonly PeriodType $period_type,
        public readonly int $cycle_start_date,
        public readonly string $start_date,
        public readonly ?int $start_posting_from // 1 - 12
    ) {}
}
