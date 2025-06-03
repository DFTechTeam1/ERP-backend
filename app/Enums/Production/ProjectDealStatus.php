<?php

namespace App\Enums\Production;

enum ProjectDealStatus: int
{
    case Draft = 0;
    case Active = 1;

    public function label()
    {
        return match ($this) {
            self::Draft => __('global.draft'),
            self::Active => __('global.active'),
        };
    }

    public function color()
    {
        return match ($this) {
            self::Draft => 'light-blue-lighten-3',
            self::Active => 'success',
        };
    }
}
