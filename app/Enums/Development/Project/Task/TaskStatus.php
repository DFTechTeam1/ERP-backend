<?php

namespace App\Enums\Development\Project\Task;

enum TaskStatus: int
{
    case WaitingApproval = 1;
    case InProgress = 2;
    case Completed = 3;
    case Revise = 4;
    case OnHold = 5;
    case CheckByPm = 6;
    case Draft = 7;

    public function label(): string
    {
        return match ($this) {
            self::WaitingApproval => __('global.waitingApproval'),
            self::InProgress => __('global.onProgress'),
            self::Completed => __('global.completed'),
            self::Revise => __('global.revise'),
            self::OnHold => __('global.onHold'),
            self::CheckByPm => __('global.checkByPm'),
            self::Draft => __('global.draft'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::WaitingApproval => 'secondary',
            self::InProgress => 'primary',
            self::Completed => 'success',
            self::Revise => 'red',
            self::OnHold => 'warning',
            self::Draft => 'dark',
            self::CheckByPm => 'info',
        };
    }
}
