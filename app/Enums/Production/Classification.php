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

    public function color()
    {
        return match ($this) {
            static::gradeS => 'yellow-darken-2',
            static::gradeA => 'deep-orange-lighten-3',
            static::gradeB => 'green-lighten-3',
            static::gradeC => 'teal-lighten-3',
            static::gradeD => 'light-blue-lighten-4',
        };
    }
}
