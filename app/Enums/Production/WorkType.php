<?php

namespace App\Enums\Production;

enum WorkType: string
{
    case OnProgress = 'on_progress';
    case Assigned = 'assigned';
    case CheckByPm = 'check_by_pm';
    case Revise = 'revise';
    case Finish = 'finish';

    public function label()
    {
        return match ($this) {
            static::OnProgress => __("global.approved"),
            static::Assigned => __("global.waitingApproval"),
            static::CheckByPm => __("global.waitingApproval"),
            static::Revise => __("global.waitingApproval"),
            static::Finish => __("global.waitingApproval"),
        };
    }
}
