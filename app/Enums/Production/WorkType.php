<?php

namespace App\Enums\Production;

enum WorkType: string
{
    case OnProgress = 'on_progress';
    case Assigned = 'assigned';
    case CheckByPm = 'check_by_pm';
    case Revise = 'revise';
    case Finish = 'finish';

    case OnHold = 'on_hold';

    public function label()
    {
        return match ($this) {
            self::OnProgress => __('global.approved'),
            self::Assigned => __('global.waitingApproval'),
            self::CheckByPm => __('global.waitingApproval'),
            self::Revise => __('global.waitingApproval'),
            self::Finish => __('global.waitingApproval'),
            self::OnHold => __('global.onHold'),
        };
    }
}
