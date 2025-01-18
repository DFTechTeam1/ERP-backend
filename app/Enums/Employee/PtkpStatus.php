<?php

namespace App\Enums\Employee;

enum PtkpStatus: string
{
    case TK0 = 'tk0';
    case TK1 = 'tk1';
    case TK2 = 'tk2';
    case TK3 = 'tk3';
    case K0 = 'k0';
    case K1 = 'k1';
    case K2 = 'k2';
    case K3 = 'k3';

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