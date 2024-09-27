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
            static::Wedding => __("global.wedding"),
            static::Engagement => __("global.engagement"),
            static::Event => __("global.event"),
            static::Birthday => __("global.birthday"),
            static::Concert => __("global.concert"),
            static::Corporate => __("global.corporate"),
            static::Exhibition => __("global.exhibition"),
        };
    }

    public static function getLabel(string $label)
    {
        dd($this);
    }
}
