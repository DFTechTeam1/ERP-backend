<?php

namespace App\Enums\Employee;

enum SalaryType: string
{
    case Monthly = '1';
    case Daily = '2';

    public function label()
    {
        return match ($this) {
            static::Monthly->value => 'Month',
            static::Daily->value => 'Daily',
        };
    }
}
