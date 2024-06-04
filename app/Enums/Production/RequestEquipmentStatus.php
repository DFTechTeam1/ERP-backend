<?php

namespace App\Enums\Production;

enum RequestEquipmentStatus: int
{
    case Ready = 1;
    case Requested = 2;
    case Decline = 3;
    case Cancel = 4;

    public function label()
    {
        return match ($this) {
            static::Ready => __("global.ready"),
            static::Requested => __("global.requested"),
            static::Decline => __("global.decline"),
            static::Cancel => __('global.canceled'),
        };
    }

    public function color()
    {
        return match ($this) {
            static::Ready => 'success',
            static::Requested => 'indigo-lighten-1',
            static::Decline => 'red-lighten-1',
            static::Cancel => 'red-accent-4',
        };
    }
}