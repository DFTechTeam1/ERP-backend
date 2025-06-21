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
            self::Hrd => __('global.hrd'),
            self::Master => __('global.master'),
            self::Accounting => __('global.accounting'),
            self::Inventory => __('global.inventory'),
            self::Dashboard => __('global.dashboard'),
            self::Addon => __('global.addon'),
            self::Production => __('global.production'),
        };
    }
}
