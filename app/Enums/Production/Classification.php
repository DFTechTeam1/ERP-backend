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
            static::gradeS => __("global.grade" .$this),
            static::gradeA => __("global.grade" .$this),
            static::gradeB => __("global.grade" .$this),
            static::gradeC => __("global.grade" .$this),
            static::gradeD => __("global.grade" .$this),
        };
    }
}
