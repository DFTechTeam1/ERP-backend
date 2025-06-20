<?php

namespace App\Enums\Production;

enum ShowreelsStatus: int
{
    case Approved = 1;
    case WaitingApproval = 2;

    public function label()
    {
        return match ($this) {
            self::Approved => __('global.approvedByClient'),
            self::WaitingApproval => __('global.waitingApprovalClient'),
        };
    }

    public function color()
    {
        return match ($this) {
            self::Approved => 'success',
            self::WaitingApproval => 'deep-purple-lighten-1',
        };
    }
}
