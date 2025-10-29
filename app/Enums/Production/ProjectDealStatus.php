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
            self::Draft => 'amber-darken-2',
            self::Final => 'green-darken-1',
            self::Temporary => 'blue-darken-1',
            self::Canceled => 'red-darken-1',
        };
    }

    public function icon()
    {
        return match ($this) {
            self::Draft => 'mdiPlain:mdi-pencil-outline',
            self::Final => 'mdiPlain:mdi-check-circle',
            self::Temporary => 'mdiPlain:mdi-clock-outline',
            self::Canceled => 'mdiPlain:mdi-cancel',
        };
    }
}
