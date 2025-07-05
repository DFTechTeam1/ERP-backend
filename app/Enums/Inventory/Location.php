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
            self::InUser => __('global.inUser'),
            self::InWarehouse => __('global.inWarehouse'),
            self::Outgoing => __('global.onVenue'),
        };
    }

    public function badgeColor()
    {
        return match ($this) {
            self::InUser => 'success',
            self::InWarehouse => 'secondary',
            self::Outgoing => 'warning',
        };
    }
}
