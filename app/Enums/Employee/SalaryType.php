<?php

namespace App\Enums\Employee;

enum SalaryType: string
{
    case Monthly = 'month';
    case Daily = 'daily';

    public function label()
    {
        return match ($this) {
            static::Monthly => 'Month',
            static::Daily => 'Daily',
        };
    }
}