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
            self::Requested => __('global.requested'),
            self::Approved => __('global.approved'),
            self::Rejected => __('global.rejected'),
            self::Closed => __('global.closed'),
        };
    }

    public function badgeColor()
    {
        return match ($this) {
            self::Requested => 'primary',
            self::Approved => 'info',
            self::Rejected => 'danger',
            self::Closed => 'secondary',
        };
    }
}
