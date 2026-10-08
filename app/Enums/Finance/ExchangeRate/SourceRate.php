<?php

namespace App\Enums\Finance\ExchangeRate;

enum SourceRate: string
{
    case System = 'system';
    case Manual = 'manual';
}
