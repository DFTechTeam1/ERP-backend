<?php

namespace App\Enums\Hrd\Signature\Template;

enum DocumentFileStatus: int
{
    case Active = 1;
    case PendingReview = 2;
    case Rejected = 3;
    case Archived = 4;
}
