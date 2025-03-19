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
            static::Gross => 'Taxable',
            static::GrossUp => 'Non-Taxable',
            static::Netto => 'Netto'
        };
    }

}
