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
            self::Asset3D => __('global.asset3d'),
            self::Compositing => __('global.compositing'),
            self::Animating => __('global.animating'),
            self::Finalize => __('global.finalize'),
        };
    }

    public function color()
    {
        return match ($this) {
            self::Asset3D => 'primary',
            self::Compositing => 'cyan',
            self::Animating => 'teal-lighten-3',
            self::Finalize => 'lime-lighten-1',
        };
    }
}
