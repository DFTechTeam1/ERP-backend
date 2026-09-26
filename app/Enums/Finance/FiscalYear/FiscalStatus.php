<?php

namespace App\Enums\Finance\FiscalYear;

enum FiscalStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}
