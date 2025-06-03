<?php

namespace App\Enums\Production;

enum EventType: string
{
    case Wedding = 'wedding';
    case Engagement = 'engagement';
    case Event = 'event';
    case Birthday = 'birthday';
    case Concert = 'concert';
    case Corporate = 'corporate';
    case Exhibition = 'exhibition';

    public function label()
    {
        return match ($this) {
            self::Wedding => __('global.wedding'),
            self::Engagement => __('global.engagement'),
            self::Event => __('global.event'),
            self::Birthday => __('global.birthday'),
            self::Concert => __('global.concert'),
            self::Corporate => __('global.corporate'),
            self::Exhibition => __('global.exhibition'),
        };
    }

    public static function getLabel(string $label)
    {
        dd($this);
    }
}
