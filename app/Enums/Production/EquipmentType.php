<?php

namespace App\Enums\Production;

enum EquipmentType: string
{
    case Lasika = 'lasika';
    case Others = 'others';

    public function label()
    {
        return match ($this) {
            static::Lasika => 'Lasika',
            static::Others => 'Others',
        };
    }
}
