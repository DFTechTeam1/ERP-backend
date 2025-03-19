<?php

namespace App\Enums\Employee;

enum JhtConfiguration: int
{
    case NotPaid = 0;
    case PaidByCompany = 1;
    case PaidByEmployee = 2;
    case Default = 3;

    public function label(): string
    {
        return match ($this) {
            static::NotPaid => 'NotPaid',
            static::PaidByCompany => 'Paid by Company',
            static::PaidByEmployee => 'Paid by Employee',
            static::Default => 'Default'
        };
    }

}
