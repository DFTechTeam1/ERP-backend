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
}
