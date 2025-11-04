<?php

namespace App\Enums\Interactive;

enum InteractiveTaskStatus: int
{
    case Pending = 1;
    case InProgress = 2;
    case Completed = 3;
    case OnHold = 4;
    case Cancelled = 5;
    case Draft = 6;
    case WaitingApproval = 7;
    case Revise = 8;
    case CheckByPm = 9;

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('global.pending'),
            self::InProgress => __('global.onProgress'),
            self::Completed => __('global.completed'),
            self::OnHold => __('global.onHold'),
            self::Cancelled => __('global.cancelled'),
            self::Draft => __('global.draft'),
            self::WaitingApproval => __('global.waitingApproval'),
            self::Revise => __('global.revise'),
            self::CheckByPm => __('global.checkByPm'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::InProgress => 'primary',
            self::Completed => 'success',
            self::OnHold => 'warning',
            self::Cancelled => 'red',
            self::Draft => 'secondary',
            self::WaitingApproval => 'secondary',
            self::Revise => 'red',
            self::CheckByPm => 'info',
        };
    }
}
