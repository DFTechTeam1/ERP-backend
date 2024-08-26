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

    public function label()
    {
        return match ($this) {
            static::Single => __('global.single'),
            static::Married => __('global.married'),
        };
    }
}
