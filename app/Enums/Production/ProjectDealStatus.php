<?php

namespace App\Enums\Production;

enum ProjectDealStatus: int
{
    case Draft = 0;
    case Final = 1;
    case Temporary = 2;
    
    public function label()
    {
        return match ($this) {
            self::Draft => __('global.draft'),
            self::Final => __('global.final'),
            self::Temporary => __('global.temporary'),
        };
    }

    public function color()
    {
        return match ($this) {
            self::Draft => 'grey-darken-2',
            self::Final => 'green-lighten-1',
            self::Temporary => 'blue-lighten-3',
        };
    }
}
