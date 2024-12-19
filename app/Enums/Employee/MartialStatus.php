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

    public static function getMartialStatus(string $code)
    {
        switch ($code) {
            case self::Single->value:
                $output = __('global.single');
                break;

            case self::Married->value:
                $output = __('global.married');
                break;
            
            default:
                $output = '-';
                break;
        }

        return $output;
    }

    public function label()
    {
        return match ($this) {
            static::Single => __('global.single'),
            static::Married => __('global.married'),
        };
    }
}
