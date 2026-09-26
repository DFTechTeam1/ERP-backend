<?php

namespace App\Enums\Finance\CostCenter;

enum CostCenterType: string
{
    case Department = 'department';
    case Division = 'division';
    case Project = 'project';
    case Branch = 'branch';
    case Other = 'other';
}
