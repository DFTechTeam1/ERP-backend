<?php

namespace App\Enums\Development\Project;

enum ProjectStatus: int {
    case Active = 1;
    case Completed = 2;
    case OnHold = 3;
    case Cancelled = 4;

    public function label(): string
    {
        return match ($this) {
            self::Active => __('global.active'),
            self::Completed => __('global.completed'),
            self::OnHold => __('global.onHold'),
            self::Cancelled => __('global.cancelled')
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Completed => 'primary',
            self::OnHold => 'warning',
            self::Cancelled => 'danger'
        };
    }
}