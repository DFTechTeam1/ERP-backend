<?php

namespace App\Enums\Inventory;

enum EventEquipmentStatus: int
{
    case Requested = 1;
    case Processed = 2;

    public function label()
    {
        return match ($this) {
            static::Requested => __('global.requested'),
            static::Processed => __('global.processed'),
        };
    }
}
