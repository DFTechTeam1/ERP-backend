<?php

namespace App\Enums\Production;

enum TaskType: string
{
    case Asset3D = 'asset3d';
    case Compositing = 'compositing';
    case Animating = 'animating';
    case Finalize = 'finalize';

    public function label()
    {
        return match ($this) {
            static::Asset3D => __("global.asset3d"),
            static::Compositing => __("global.compositing"),
            static::Animating => __("global.animating"),
            static::Finalize => __("global.finalize"),
        };
    }

    public function color()
    {
        return match ($this) {
            static::Asset3D => 'primary',
            static::Compositing => 'cyan',
            static::Animating => 'teal-lighten-3',
            static::Finalize => 'lime-lighten-1',
        };
    }
}
