<?php

namespace App\Enums\Finance\FiscalYear;

enum PeriodType: string
{
    case Calendar = 'calendar';
    case Custom = 'custom';
}
