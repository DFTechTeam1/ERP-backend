<?php

namespace App\Enums\Cache;

enum CacheKey: string
{
    case EmployeeList = 'employeesCache';
    case InventoryList = 'inventoriesCache';
    case ProjectNeedToBeComplete = 'projectToBeComplete';
    case HrDashboardEmoloyeeList = 'hrDashboardEmployeeList';
    case HrDashboardEmploymentStatus = 'hrDashboardEmploymentStatus';
    case HrDashboardLoS = 'hrDashboardLoS';
    case HrDashboardActiveStaff = 'hrDashboardActiveStaff';
    case HrDashboardGenderDiversity = 'hrDashboardGenderDiversity';
    case HrDashboardJobLevel = 'hrDashboardJobLevel';
    case HrDashboardAgeAverage = 'hrDashboardAgeAverage';

    case MarketingList = 'marketingList';
    case CustomerList = 'customerList';

    // price settings
    case PriceGuideSetting = 'priceGuideSetting';
    case MainLedFormula = 'mainLedFormula';
    case PrefuncLedFormula = 'prefuncLedFormula';
    case HighSeasonFormula = 'highSeasonFormula';
    case EquipmentFormula = 'equipmentFormula';
    case MaxDiscountFormula = 'maxDiscountFormula';
    case MaxMarkupFormula = 'maxMarkupFormula';

    // price changes reason
    case PriceChangeReasons = 'priceChangeReasons';

    case ProjectCount = 'projectCount';
    case ProjectDealIdentifierNumber = 'projectDealIdentifierNumber';
}
