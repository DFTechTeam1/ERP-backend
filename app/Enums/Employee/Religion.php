<?php

namespace App\Enums\Employee;

enum Religion: string
{
    case Islam = 'islam';
    case Kristen = 'kristen';
    case Khatolik = 'katholik';
    case Hindu = 'hindu';
    case Budha = 'budha';
    case Konghucu = 'konghucu';

    public static function generateReligion(string $data)
    {
        if ($data == 'Islam') {
            return self::Islam;
        } else if ($data == 'Kristen') {
            return self::Kristen;
        } else if ($data == 'Katholik') {
            return self::Khatolik;
        } else if ($data == 'Hindu') {
            return self::Hindu;
        } else if ($data == 'Budha') {
            return self::Budha;
        }
    }
}
