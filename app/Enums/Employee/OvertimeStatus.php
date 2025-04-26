<?php

namespace App\Enums\Employee;

enum OvertimeStatus: int
{
    case Eligible = 1;
    case NonEligible = 2;

    public function label(): string
    {
        return match ($this) {
            static::Eligible => 'Eligible',
            static::NonEligible => 'NonEligible',
        };
    }

}
