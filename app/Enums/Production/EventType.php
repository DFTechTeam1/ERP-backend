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
            static::Wedding => __("global." .$this),
            static::Engagement => __("global." .$this),
            static::Event => __("global." .$this),
            static::Birthday => __("global." .$this),
            static::Concert => __("global." .$this),
            static::Corporate => __("global." .$this),
            static::Exhibition => __("global." .$this),
        };
    }
}
