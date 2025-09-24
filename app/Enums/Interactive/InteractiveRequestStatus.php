<?php

namespace App\Enums\Interactive;

enum InteractiveRequestStatus: string
{
    case Pending = '1';
    case Approved = '2';
    case Rejected = '3';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('global.pending'),
            self::Approved => __('global.approved'),
            self::Rejected => __('global.rejected'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }
}
