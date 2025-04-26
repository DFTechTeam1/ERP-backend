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
            static::TK0 => 'TK/0',
            static::TK1 => 'TK/1',
            static::TK2 => 'TK/2',
            static::TK3 => 'TK/3',
            static::K0 => 'K/0',
            static::K1 => 'K/1',
            static::K2 => 'K/2',
            static::K3 => 'K/3',
        };
    }
}
