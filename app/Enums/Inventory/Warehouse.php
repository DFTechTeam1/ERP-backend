<?php

namespace App\Enums\Inventory;

enum Warehouse: int
{
    case Office = 1;
    case Entertainment = 2;

    public function label()
    {
        return match ($this) {
            self::Office => __('global.office'),
            self::Entertainment => __('global.entertaintment'),
        };
    }

    public function color()
    {
        return match ($this) {
            self::Office => 'success',
            self::Entertainment => 'success',
        };
    }
}
