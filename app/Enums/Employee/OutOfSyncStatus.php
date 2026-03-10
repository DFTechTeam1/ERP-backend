<?php

namespace App\Enums\Employee;

enum OutOfSyncStatus: string
{
    case Synced = 'synced';
    case OutOfSync = 'out_of_sync';
}
