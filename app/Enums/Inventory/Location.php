<?php

namespace App\Enums\Inventory;

enum Location: int
{
    case InUser = 1;
    case InWarehouse = 2;
    case Outgoing = 3;

    public function label()
    {
        return match ($this) {
            static::InUser => __('global.inUser'),
            static::InWarehouse => __('global.inWarehouse'),
            static::Outgoing => __('global.onVenue'),
        };
    }

    public function badgeColor()
    {
        return match ($this) {
            static::InUser => 'success',
            static::InWarehouse => 'secondary',
            static::Outgoing => 'warning',
        };
    }
}
