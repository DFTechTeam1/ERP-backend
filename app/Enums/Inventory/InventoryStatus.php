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
            static::InUse => __('global.inUse'),
            static::InRepair => __('global.inRepair'),
            static::Broke => __('global.broke'),
            static::Disposal => __('global.disposal'),
            static::OnSite => __('global.onSite'),
            static::OnWarehouse => __('global.onWarehouse'),
        };
    }

    public function badgeColor()
    {
        return match ($this) {
            static::InUse => 'bg-light-blue',
            static::InRepair => 'bg-light-yellow',
            static::Broke => 'bg-black',
            static::Disposal => 'bg-light-red',
            static::OnSite => 'bg-teal-darken-4',
            static::OnWarehouse => 'bg-green-darken-2'
        };
    }
}
