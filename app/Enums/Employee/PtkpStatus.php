<?php

namespace App\Enums\Employee;

enum PtkpStatus: string
{
    case TK0 = '1';
    case TK1 = '2';
    case TK2 = '3';
    case TK3 = '4';
    case K0 = '5';
    case K1 = '6';
    case K2 = '7';
    case K3 = '8';

    public function label()
    {
        return match ($this) {
            self::TK0 => 'TK/0',
            self::TK1 => 'TK/1',
            self::TK2 => 'TK/2',
            self::TK3 => 'TK/3',
            self::K0 => 'K/0',
            self::K1 => 'K/1',
            self::K2 => 'K/2',
            self::K3 => 'K/3',
        };
    }
}
