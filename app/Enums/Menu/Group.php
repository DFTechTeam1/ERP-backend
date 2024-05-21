<?php

namespace App\Enums\Menu;

enum Group: int
{
    case Hrd = 1;
    case Master = 2;
    case Accounting = 3;
    case Inventory = 4;
    case Dashboard = 5;
    case Addon = 6;
    case Production = 7;

    public function label()
    {
        return match ($this) {
            static::Hrd => __('global.hrd'),
            static::Master => __('global.master'),
            static::Accounting => __('global.accounting'),
            static::Inventory => __('global.inventory'),
            static::Dashboard => __('global.dashboard'),
            static::Addon => __('global.addon'),
            static::Production => __('global.production'),
        };
    }
}
