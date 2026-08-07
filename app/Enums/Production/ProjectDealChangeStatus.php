<?php

namespace App\Enums\Production;

enum ProjectDealChangeStatus: int
{
    case Pending = 1;
    case Approved = 2;
    case Rejected = 3;

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('global.pending'),
            self::Approved => __('global.approved'),
            self::Rejected => __('global.rejected'),
        };
    }
}
