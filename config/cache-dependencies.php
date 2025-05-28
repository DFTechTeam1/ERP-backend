<?php

use App\Enums\Cache\CacheKey;
use Modules\Hrd\Models\Employee;

return [
    Employee::class => [
        // Contain all active employee that will be used in the HR Dashboard Element
        CacheKey::HrDashboardEmoloyeeList->value,

        CacheKey::HrDashboardEmploymentStatus->value,
        CacheKey::HrDashboardLoS->value,
        CacheKey::HrDashboardActiveStaff->value,
        CacheKey::HrDashboardGenderDiversity->value,
        CacheKey::HrDashboardJobLevel->value,
        CacheKey::HrDashboardAgeAverage->value
    ],
    \Modules\Company\Models\Setting::class => [
        CacheKey::PriceGuideSetting->value,
    ]
];
