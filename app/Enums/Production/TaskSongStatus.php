<?php

namespace App\Enums\Production;

enum TaskSongStatus: int
{
    case Active = 1;
    case OnProgress = 2;
    case Revise = 3;

    public function label()
    {
        return match ($this) {
            static::Active => __("global.active"),
            static::OnProgress => __("global.onProgress"),
            static::Revise => __("global.revise"),
        };
    }
}