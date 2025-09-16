<?php

namespace App\Enums\Production;

enum ProjectDealChangeStatus: int
{
    case Pending = 1;
    case Approved = 2;
    case Rejected = 3;
}
