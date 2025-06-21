<?php

namespace App\Enums\Production;

enum TransferTeamStatus: int
{
    case Requested = 1;
    case Approved = 2;
    case Reject = 3;
    case Completed = 4;
    case Canceled = 5;
    case ApprovedWithAlternative = 6;

    public function label()
    {
        return match ($this) {
            self::Requested => __('global.requested'),
            self::Approved => __('global.approved'),
            self::Reject => __('global.reject'),
            self::Completed => __('global.completed'),
            self::Canceled => __('global.canceled'),
            self::ApprovedWithAlternative => __('global.approvedWithAlternative'),
        };
    }

    public function color()
    {
        return match ($this) {
            self::Requested => 'primary',
            self::Approved => 'success',
            self::Reject => 'red-darken-1',
            self::Completed => 'light-green-lighten-1',
            self::Canceled => 'warning',
            self::ApprovedWithAlternative => 'success',
        };
    }
}
