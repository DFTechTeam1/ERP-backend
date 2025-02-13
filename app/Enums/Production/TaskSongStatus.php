<?php

namespace App\Enums\Production;

enum TaskSongStatus: int
{
    case Active = 1;
    case OnProgress = 2;
    case Revise = 3;
    case OnFirstReview = 4;
    case OnLastReview = 5;
    case Completed = 6;

    public function label()
    {
        return match ($this) {
            static::Active => __("global.active"),
            static::OnProgress => __("global.onProgress"),
            static::Revise => __("global.revise"),
            static::OnFirstReview => __('global.checkByPm'),
            static::OnLastReview => __('global.checkByPm'),
            static::Completed => __("global.completed")
        };
    }

    public static function getLabel(int $value)
    {
        return match ($value) {
            self::Active->value => __('global.active'),
            self::OnProgress->value => __('global.onProgress'),
            self::Revise->value => __('global.revise'),
            self::OnFirstReview->value => __('global.checkByPm'),
            self::OnLastReview->value => __('global.checkByPm'),
            self::Completed->value => __('global.completed')
        };
    }

    public static function getColor(int $value)
    {
        return match ($value) {
            self::Active->value => 'primary',
            self::OnProgress->value => 'success',
            self::Revise->value => 'orange-lighten-3',
            self::OnFirstReview->value => 'indigo-lighten-1',
            self::OnLastReview->value => 'indigo-lighten-1',
            self::Completed->value => 'success'
        };
    }
}