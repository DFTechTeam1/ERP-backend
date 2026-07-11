<?php

namespace App\Enums\Hrd\Signature\Template;

enum Status: int
{
    case Completed = 1;
    case Awaiting = 2;
    case NeedSign = 3;
}
