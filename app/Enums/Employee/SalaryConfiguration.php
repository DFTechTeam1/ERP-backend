<?php

namespace App\Enums\Employee;

enum SalaryConfiguration: int
{
    case Taxable = 1;
    case NonTaxable = 2;

    public function label()
    {
        return match ($this) {
            self::Taxable => 'Taxable',
            self::NonTaxable => 'Non-Taxable',
        };
    }
}
