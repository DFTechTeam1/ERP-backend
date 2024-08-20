<?php

namespace App\Enums\Production;

enum RequestEquipmentStatus: int
{
    case Ready = 1;
    case Requested = 2;
    case Decline = 3;
    case Cancel = 4;
    case Return = 5;
    case OnEvent = 6;
    case CompleteAndNotReturn = 7;

    public function label()
    {
        return match ($this) {
            static::Ready => __("global.ready"),
            static::Requested => __("global.requested"),
            static::Decline => __("global.decline"),
            static::Cancel => __('global.canceled'),
            static::Return => __('global.returned'),
            static::OnEvent => __('global.onEvent'),
            static::CompleteAndNotReturn => __('global.completeAndNotYetReturned'),
        };
    }

    public function color()
    {
        return match ($this) {
            static::Ready => 'success',
            static::Requested => 'indigo-lighten-1',
            static::Decline => 'red-lighten-1',
            static::Cancel => 'red-accent-4',
            static::Return => 'primary',
            static::OnEvent => 'deep-purple-darken-4',
            static::CompleteAndNotReturn => 'lime-darken-2',
        };
    }
}