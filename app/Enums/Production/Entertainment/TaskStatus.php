<?php

namespace App\Enums\Production\Entertainment;

enum TaskStatus: int
{
    case WaitingApproval = 1;
    case OnGoing = 2;
    case OnHold = 3;
    case Revise = 4;
    case CheckByPm = 5;
    case Complete = 6;
}
