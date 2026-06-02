<?php

namespace App\Enums\Production;

enum ProjectLeadStatus: string
{
    case ACTIVE = 'active';
    case CANCELLED = 'cancelled';
}
