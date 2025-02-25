<?php

namespace App\Enums\Cache;

enum CacheKey: string
{
    case EmployeeList = 'employeesCache';
    case InventoryList = 'inventoriesCache';
    case ProjectNeedToBeComplete = 'projectToBeComplete';
}
