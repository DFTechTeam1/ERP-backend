<?php

namespace App\Enums\Production;

enum TaskPicStatus: int
{
    case Approved = 1;
    case WaitingApproval = 2;

    public function label()
    {
        return match ($this) {
            static::Approved => __("global.approved"),
            static::WaitingApproval => __("global.waitingApproval"),
        };
    }
}
