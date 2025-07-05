<?php

namespace App\Enums\Employee;

enum TaxConfiguration: int
{
    case Gross = 1;
    case GrossUp = 2;
    case Netto = 3;

    public function label()
    {
        return match ($this) {
            self::Gross => 'Taxable',
            self::GrossUp => 'Non-Taxable',
            self::Netto => 'Netto'
        };
    }
}
