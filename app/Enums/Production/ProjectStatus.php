<?php

namespace App\Enums\Production;

enum ProjectStatus: int
{
    case OnGoing = 1;
    case Draft = 2;
    case Revise = 3;
    case WaitingApprovalClient = 4;
    case ApprovedByClient = 5;
    case Completed = 6;
    case ReadyToGo = 7;

    public function label()
    {
        return match ($this) {
            static::OnGoing => __("global.onGoing"),
            static::Draft => __("global.draft"),
            static::Revise => __("global.revise"),
            static::WaitingApprovalClient => __("global.waitingApprovalClient"),
            static::ApprovedByClient => __("global.approvedByClient"),
            static::Completed => __("global.completed"),
            static::ReadyToGo => __("global.readyToGo"),
        };
    }

    public function color()
    {
        return match ($this) {
            static::OnGoing => 'deep-purple-lighten-3',
            static::Draft => 'gray-lighten-3',
            static::Revise => 'deep-orange-lighten-2',
            static::WaitingApprovalClient => 'teal-lighten-3',
            static::ApprovedByClient => 'cyan-lighten-3',
            static::Completed => 'green-lighten-2',
            static::ReadyToGo => 'success',
        };
    }
}
