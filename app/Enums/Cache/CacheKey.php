<?php

namespace App\Enums\Cache;

enum CacheKey: string
{
    case EmployeeList = 'employeesCache';
    case InventoryList = 'inventoriesCache';
    case ProjectNeedToBeComplete = 'projectToBeComplete';
    case HrDashboardEmoloyeeList = "hrDashboardEmployeeList";
    case HrDashboardEmploymentStatus = "hrDashboardEmploymentStatus";
    case HrDashboardLoS = "hrDashboardLoS";
    case HrDashboardActiveStaff = "hrDashboardActiveStaff";
    case HrDashboardGenderDiversity = "hrDashboardGenderDiversity";
    case HrDashboardJobLevel = "hrDashboardJobLevel";
    case HrDashboardAgeAverage = "hrDashboardAgeAverage";
}
