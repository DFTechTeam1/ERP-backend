<?php

namespace App\Enums\Production;

enum TaskStatus: int
{
    case OnProgress = 1;
    case CheckByPm = 2;
    case Revise = 3;
    case Completed = 4;
    case WaitingApproval = 5;

    public function label()
    {
        return match ($this) {
            static::OnProgress => __("global.onProgress"),
            static::CheckByPm => __("global.checkByPm"),
            static::Revise => __("global.revise"),
            static::Completed => __("global.completed"),
            static::WaitingApproval => __("global.waitingApproval"),
        };
    }
}
