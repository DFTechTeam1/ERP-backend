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
    case Canceled = 8;
    case PartialComplete = 9;

    public function label()
    {
        return match ($this) {
            self::OnGoing => __('global.onGoing'),
            self::Draft => __('global.draft'),
            self::Revise => __('global.revise'),
            self::WaitingApprovalClient => __('global.waitingApprovalClient'),
            self::ApprovedByClient => __('global.approvedByClient'),
            self::Completed => __('global.completed'),
            self::ReadyToGo => __('global.readyToGo'),
            self::Canceled => __('global.canceled'),
            self::PartialComplete => __('global.partialComplete')
        };
    }

    public function color()
    {
        return match ($this) {
            self::OnGoing => 'deep-purple-lighten-3',
            self::Draft => 'gray-lighten-3',
            self::Revise => 'deep-orange-lighten-2',
            self::WaitingApprovalClient => 'teal-lighten-3',
            self::ApprovedByClient => 'cyan-lighten-3',
            self::Completed => 'green-lighten-2',
            self::ReadyToGo => 'success',
            self::Canceled => 'brown-darken-2',
            self::PartialComplete => 'green-darken-1'
        };
    }
}
