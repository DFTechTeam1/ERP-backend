<?php

namespace App\Enums\Development\Project;

enum ProjectStatus: int {
    case Active = 1;
    case Completed = 2;
    case OnHold = 3;
    case Cancelled = 4;
}