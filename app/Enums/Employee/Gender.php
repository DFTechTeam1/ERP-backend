<?php

namespace App\Enums\Employee;

enum Gender: string
{
    case Female = 'female';
    case Male = 'male';

    public static function generateGender(string $data)
    {
        if ($data == 'Female') {
            return self::Female;
        } else if ($data == 'Male') {
            return self::Male;
        }
    }

    public static function getGender(string $code)
    {
        switch ($code) {
            case self::Female->value:
                $output = __('global.female');
                break;

            case self::Male->value:
                $output = __('global.male');
                break;
            
            default:
                $output = '-';
                break;
        }

        return $output;}

    public function label()
    {
        return match ($this) {
            static::Female => __('global.female'),
            static::Male => __('global.male'),
        };
    }
}
