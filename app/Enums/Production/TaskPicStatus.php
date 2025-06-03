<?php

namespace App\Enums\Production;

enum TaskPicStatus: int
{
    case Approved = 1;
    case WaitingApproval = 2;
    case Revise = 3;
    case WaitingToDistribute = 4;

    public function label()
    {
        return match ($this) {
            self::Approved => __('global.approved'),
            self::WaitingApproval => __('global.waitingApproval'),
            self::Revise => __('global.revise'),
            self::WaitingToDistribute => __('global.waitingToDistributeToModeller')
        };
    }
}
