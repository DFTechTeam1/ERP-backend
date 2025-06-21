<?php

namespace App\Enums\Employee;

enum JpConfiguration: int
{
    case NotPaid = 0;
    case PaidByCompany = 1;
    case PaidByEmployee = 2;
    case Default = 3;

    public function label(): string
    {
        return match ($this) {
            self::NotPaid => 'NotPaid',
            self::PaidByCompany => 'Paid by Company',
            self::PaidByEmployee => 'Paid by Employee',
            self::Default => 'Default'
        };
    }
}
