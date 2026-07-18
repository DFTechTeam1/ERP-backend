<?php

namespace App\Enums\Hrd\Signature\Template;

enum Status: int
{
    case Completed = 1;
    case Awaiting = 2;
    case NeedSign = 3;

    public function label(): string
    {
        return match ($this) {
            self::Completed => __('global.completed'),
            self::Awaiting => __('global.awaitingSign'),
            self::NeedSign => __('global.needSign'),
        };
    }
}
