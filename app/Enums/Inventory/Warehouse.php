<?php

namespace App\Enums\Inventory;

enum Warehouse: int
{
    case Office = 1;
    case Entertainment = 2;

    public function label()
    {
        return match ($this) {
            static::Office => __('global.office'),
            static::Entertainment => __('global.entertaintment'),
        };
    }

    public function color()
    {
        return match ($this) {
            static::Office => 'success',
            static::Entertainment => 'success',
        };
    }
}
