<?php

namespace App\Enums\Inventory;

enum InventoryStatus: int
{
    case InUse = 1;
    case InRepair = 2;
    case Broke = 3;
    case Disposal = 4;
    case OnSite = 5;
    case OnWarehouse = 6;

    public function label()
    {
        return match ($this) {
            self::InUse => __('global.inUse'),
            self::InRepair => __('global.inRepair'),
            self::Broke => __('global.broke'),
            self::Disposal => __('global.disposal'),
            self::OnSite => __('global.onSite'),
            self::OnWarehouse => __('global.onWarehouse'),
        };
    }

    public function badgeColor()
    {
        return match ($this) {
            self::InUse => 'bg-light-blue',
            self::InRepair => 'bg-light-yellow',
            self::Broke => 'bg-black',
            self::Disposal => 'bg-light-red',
            self::OnSite => 'bg-teal-darken-4',
            self::OnWarehouse => 'bg-green-darken-2'
        };
    }
}
