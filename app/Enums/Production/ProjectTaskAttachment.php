<?php

namespace App\Enums\Production;

enum ProjectTaskAttachment: int
{
    case Media = 1;
    case TaskLink = 2;
    case ExternalLink = 3;

    public function label()
    {
        return match ($this) {
            self::Media => __('global.media'),
            self::TaskLink => __('global.taskLink'),
            self::ExternalLink => __('global.externalLink'),
        };
    }
}
