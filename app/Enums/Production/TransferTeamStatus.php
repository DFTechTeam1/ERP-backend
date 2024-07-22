<?php

namespace App\Enums\Production;

enum TransferTeamStatus: int
{
    case Requested = 1;
    case Approved = 2;
    case Reject = 3;
    case Completed = 4;
    case Canceled = 5;

    public function label()
    {
        return match ($this) {
            static::Requested => __("global.requested"),
            static::Approved => __('global.approved'),
            static::Reject => __('global.reject'),
            static::Completed => __('global.completed'),
            static::Canceled => __('global.canceled'),
        };
    }

    public function color()
    {
        return match ($this) {
            static::Requested => 'primary',
            static::Approved => 'success',
            static::Reject => 'red-darken-1',
            static::Completed => 'light-green-lighten-1',
            static::Canceled => 'warning',
        };
    }
}
