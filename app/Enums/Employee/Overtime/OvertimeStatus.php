<?php

namespace App\Enums\Employee\Overtime;

enum OvertimeStatus: int
{
    case Draft = 0;
    case Unverified = 1;
    case PartiallyApproved = 2;
    case FullyApproved = 3;
    case Revised = 4;
    case Rejected = 5;
    case Cancelled = 8;
    case Closed = 9;
}
