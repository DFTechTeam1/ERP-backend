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

    public static function generateTalentaVariable(string $value): int
    {
        switch ($value) {
            case self::Islam->value:
                $output = 2;
                break;

            case self::Kristen->value:
                $output = 3;
                break;

            case self::Khatolik->value:
                $output = 1;
                break;

            case self::Hindu->value:
                $output = 5;
                break;

            case self::Budha->value:
                $output = 4;
                break;

            case self::Konghucu->value:
                $output = 7;
                break;

            default:
                $output = 7;
                break;
        }

        return $output;
    }

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
        } else {
            return '-';
        }
    }

    public static function getReligion(string $code)
    {
        switch ($code) {
            case self::Islam->value:
                $output = 'Islam';
                break;

            case self::Kristen->value:
                $output = 'Kristen';
                break;

            case self::Khatolik->value:
                $output = 'Khatolik';
                break;

            case self::Hindu->value:
                $output = 'Hindu';
                break;

            case self::Budha->value:
                $output = 'Budha';
                break;

            case self::Konghucu->value:
                $output = 'Konghucu';
                break;

            default:
                $output = '-';
                break;
        }

        return $output; }
}
