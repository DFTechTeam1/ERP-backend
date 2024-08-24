<?php

namespace App\Enums\Employee;

enum RelationFamily: string
{
    case Father = 'father';
    case Mother = 'mother';
    case Sibling = 'sibling';
    case Child = 'child';
    case Other = 'other';

    public function label()
    {
        return match ($this) {
            static::Father => __('global.father'),
            static::Mother => __('global.mother'),
            static::Sibling => __('global.sibling'),
            static::Child => __('global.child'),
            static::Other => __('global.other'),
        };
    }
}
