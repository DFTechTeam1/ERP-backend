<?php

namespace App\Enums\Inventory;

enum RequestInventoryStatus: int
{
    case Requested = 1;
    case Approved = 2;
    case Rejected = 3;
    case Closed = 4;

    public function label()
    {
        return match ($this) {
            static::Requested => __('global.requested'),
            static::Approved => __('global.approved'),
            static::Rejected => __('global.rejected'),
            static::Closed => __('global.closed'),
        };
    }

    public function badgeColor()
    {
        return match ($this) {
            static::Requested => 'primary',
            static::Approved => 'primary',
            static::Rejected => 'danger',
            static::Closed => 'secondary',
        };
    }
}
