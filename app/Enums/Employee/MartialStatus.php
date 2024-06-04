<?php

namespace App\Enums\Employee;

enum MartialStatus: string
{
    case Single = 'single';
    case Married = 'married';

    public static function generateMartial(string $data)
    {
        if ($data == 'Single') {
            return self::Single;
        } else if ($data == 'Married') {
            return self::Married;
        }
    }
}
