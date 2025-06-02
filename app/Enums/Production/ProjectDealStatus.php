<?php

namespace App\Enums\Production;

enum ProjectDealStatus: int
{
    case Draft = 0;
    case Active = 1;

    public function label()
    {
        return match ($this) {
            static::Draft => __("global.draft"),
            static::Active => __("global.active"),
        };
    }

    public function color()
    {
        return match ($this) {
            static::Draft => 'light-blue-lighten-3',
            static::Active => 'success',
        };
    }
}
