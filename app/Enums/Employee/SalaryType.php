<?php

namespace App\Enums\Employee;

enum SalaryType: int
{
    case Monthly = 1;
    case Daily = 2;

    public function label()
    {
        return match ($this) {
            self::Monthly => 'Month',
            self::Daily => 'Daily',
        };
    }
}
