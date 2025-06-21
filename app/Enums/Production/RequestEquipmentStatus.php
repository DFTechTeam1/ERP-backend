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
            self::Ready => __('global.ready'),
            self::Requested => __('global.requested'),
            self::Decline => __('global.decline'),
            self::Cancel => __('global.canceled'),
            self::Return => __('global.returned'),
            self::OnEvent => __('global.onEvent'),
            self::CompleteAndNotReturn => __('global.completeAndNotYetReturned'),
        };
    }

    public function color()
    {
        return match ($this) {
            self::Ready => 'success',
            self::Requested => 'indigo-lighten-1',
            self::Decline => 'red-lighten-1',
            self::Cancel => 'red-accent-4',
            self::Return => 'primary',
            self::OnEvent => 'deep-purple-darken-4',
            self::CompleteAndNotReturn => 'lime-darken-2',
        };
    }
}
