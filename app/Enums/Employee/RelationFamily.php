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
            self::Father => __('global.father'),
            self::Mother => __('global.mother'),
            self::Sibling => __('global.sibling'),
            self::Child => __('global.child'),
            self::Other => __('global.other'),
        };
    }
}
