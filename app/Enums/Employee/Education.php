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
        } else if ($data == 'S1') {
            return self::S1;
        } else if ($data == 'Diploma') {
            return self::Diploma;
        } else if ($data == 'S2') {
            return self::S2;
        } else if ($data == 'S3') {
            return self::S3;
        }
    }
}
