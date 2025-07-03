<?php

namespace App\Enums\Production;

enum TaskStatus: int
{
    case OnProgress = 1;
    case CheckByPm = 2;
    case Revise = 3;
    case Completed = 4;
    case WaitingApproval = 5;
    case OnHold = 6;
    case WaitingDistribute = 7; // waiting lead modeller to distribute the task

    public function label()
    {
        return match ($this) {
            self::OnProgress => __('global.onProgress'),
            self::CheckByPm => __('global.checkByPm'),
            self::Revise => __('global.revise'),
            self::Completed => __('global.completed'),
            self::WaitingApproval => __('global.waitingApproval'),
            self::OnHold => __('global.onHold'),
            self::WaitingDistribute => __('global.waitingToDistributeToModeller')
        };
    }

    public function color()
    {
        return match ($this) {
            self::OnProgress => 'light-blue-lighten-3',
            self::CheckByPm => 'deep-purple-lighten-1',
            self::Revise => 'orange-darken-1',
            self::Completed => 'success',
            self::WaitingApproval => 'grey-lighten-1',
            self::WaitingDistribute => 'grey-lighten-1',
            self::OnHold => 'yellow-darken-3',
        };
    }
}
