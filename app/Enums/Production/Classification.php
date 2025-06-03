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
            self::gradeS => __('global.grades'),
            self::gradeA => __('global.gradea'),
            self::gradeB => __('global.gradeb'),
            self::gradeC => __('global.gradec'),
            self::gradeD => __('global.graded'),
        };
    }

    public function color()
    {
        return match ($this) {
            self::gradeS => 'yellow-darken-2',
            self::gradeA => 'deep-orange-lighten-3',
            self::gradeB => 'green-lighten-3',
            self::gradeC => 'teal-lighten-3',
            self::gradeD => 'light-blue-lighten-4',
        };
    }
}
