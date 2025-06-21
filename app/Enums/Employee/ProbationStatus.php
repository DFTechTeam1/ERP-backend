<?php

namespace App\Enums\Employee;

enum ProbationStatus: string
{
    case Lulus = '1';
    case TidakLulus = '2';
    case Perpanjang = '3';
    case Note = '4';

    public static function generateStatus(string $data)
    {
        if ($data == 'Lulus') {
            return self::Lulus;
        } elseif ($data == 'Tidak Lulus') {
            return self::TidakLulus;
        } elseif ($data == 'Perpanjang') {
            return self::Perpanjang;
        } elseif ($data == 'Catatan') {
            return self::Note;
        }
    }
}
