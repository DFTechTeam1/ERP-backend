<?php

namespace App\Enums\Production;

enum ShowreelsStatus: int
{
    case Approved = 1;
    case WaitingApproval = 2;

    public function label()
    {
        return match ($this) {
            static::Approved => __("global.approvedByClient"),
            static::WaitingApproval => __("global.waitingApprovalClient"),
        };
    }

    public function color()
    {
        return match ($this) {
            static::Approved => 'success',
            static::WaitingApproval => 'deep-purple-lighten-1',
        };
    }
}