<?php

namespace App\Enums\Inventory;

enum EventEquipmentType: string
{
    case Mixed = 'mixed';
    case Regular = 'regular';
    case Custom = 'custom';

    public function label()
    {
        return match ($this) {
            static::Mixed => __('global.mixed'),
            static::Regular => __('global.regular'),
            static::Custom => __('global.custom')
        };
    }
}
