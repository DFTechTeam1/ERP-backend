<?php

namespace App\Enums\Production;

enum ProjectDealStatus: int
{
    case Draft = 0;
    case Final = 1;
    case Temporary = 2;
    case Canceled = 3;
    
    public function label()
    {
        return match ($this) {
            self::Draft => __('global.draft'),
            self::Final => __('global.final'),
            self::Temporary => __('global.temporary'),
            self::Canceled => __('global.canceled'),
        };
    }

    public function color()
    {
        return match ($this) {
            self::Draft => 'grey-darken-2',
            self::Final => 'green-lighten-1',
            self::Temporary => 'blue-lighten-3',
            self::Canceled => 'grey-darken-5',
        };
    }
}
