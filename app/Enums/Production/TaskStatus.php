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
            static::OnProgress => __("global.onProgress"),
            static::CheckByPm => __("global.checkByPm"),
            static::Revise => __("global.revise"),
            static::Completed => __("global.completed"),
            static::WaitingApproval => __("global.waitingApproval"),
            static::OnHold => __("global.onHold"),
            static::WaitingDistribute => __("global.waitingToDistributeToModeller")
        };
    }

    public function color()
    {
        return match ($this) {
            static::OnProgress => 'light-blue-lighten-3',
            static::CheckByPm => 'deep-purple-lighten-1',
            static::Revise => 'orange-darken-1',
            static::Completed => 'success',
            static::WaitingApproval => 'grey-lighten-1',
            static::WaitingDistribute => 'grey-lighten-1',
            static::OnHold => 'yellow-darken-3',
        };
    }
}
