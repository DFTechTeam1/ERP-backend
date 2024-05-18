<?php

namespace App\Enums\Production;

enum Classification: string
{
    case gradeS = 's';
    case gradeA = 'a';
    case gradeB = 'b';
    case gradeC = 'c';
    case gradeD = 'd';

    public function label()
    {
        return match ($this) {
            static::gradeS => __("global.grades"),
            static::gradeA => __("global.gradea"),
            static::gradeB => __("global.gradeb"),
            static::gradeC => __("global.gradec"),
            static::gradeD => __("global.graded"),
        };
    }
}
