<?php

namespace App\Enums\Production;

enum WorktimeType: string
{
    case OnProgress = 'on_progress';
    case ReviewByPm = 'review_by_pm';
    case ReviewByClient = 'review_by_client';
}
