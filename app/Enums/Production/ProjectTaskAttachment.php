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
            static::Media => __("global.media"),
            static::TaskLink => __("global.taskLink"),
            static::ExternalLink => __("global.externalLink"),
        };
    }
}