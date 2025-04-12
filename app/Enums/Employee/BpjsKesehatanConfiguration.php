<?php

namespace App\Enums\Employee;

enum BpjsKesehatanConfiguration: int
{
    case PaidByCompany = 1;
    case PaidByEmployee = 2;
    case Default = 3;

    public function label(): string
    {
        return match ($this) {
            static::PaidByCompany => 'Paid by Company',
            static::PaidByEmployee => 'Paid by Employee',
            static::Default => 'Default'
        };
    }

}
