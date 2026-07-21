<?php

namespace App\Enums\Hrd\Signature\GenerateDocument;

enum AssignTo: string
{
    case All = 'all';
    case Division = 'division';
    case Position = 'position';
}
