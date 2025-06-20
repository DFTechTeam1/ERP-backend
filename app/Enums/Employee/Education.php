<?php

namespace App\Enums\Employee;

enum Education: string
{
    case SMP = 'smp';
    case SMA = 'sma';
    case SMK = 'smk';
    case Diploma = 'diploma';
    case S1 = 's1';
    case S2 = 's2';
    case S3 = 's3';

    public static function generateEducation(string $data)
    {
        if ($data == 'SMA/SMA/SMEA') {
            return self::SMA;
        } elseif ($data == 'S1') {
            return self::S1;
        } elseif ($data == 'Diploma') {
            return self::Diploma;
        } elseif ($data == 'S2') {
            return self::S2;
        } elseif ($data == 'S3') {
            return self::S3;
        }
    }
}
