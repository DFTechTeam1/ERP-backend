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

    public static function getLabel(int $value)
    {
        return match ($value) {
            self::Active->value => __('global.active'),
            self::OnProgress->value => __('global.onProgress'),
            self::Revise->value => __('global.revise'),
        };
    }

    public static function getColor(int $value)
    {
        return match ($value) {
            self::Active->value => 'primary',
            self::OnProgress->value => 'success',
            self::Revise->value => 'orange-lighten-3',
        };
    }
}