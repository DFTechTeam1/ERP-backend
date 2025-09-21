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
            self::Pending => __('fleet.pending'),
            self::Approved => __('fleet.approved'),
            self::Rejected => __('fleet.rejected'),
        };
    }
}
