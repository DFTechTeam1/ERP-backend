<?php

namespace App\Enums\Finance\FiscalYear;

enum AccountingPeriodStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Locked = 'locked';
}
