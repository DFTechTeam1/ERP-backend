<?php

namespace Modules\Hrd\Services;

use Modules\Company\Repository\PositionRepository;
use Modules\Hrd\Repository\EmployeeEmergencyContactRepository;
use Modules\Hrd\Repository\EmployeeFamilyRepository;
use Modules\Hrd\Repository\EmployeeRepository;

class EmployeeRepoGroup {
    public $employeeRepo;

    public $positionRepo;

    public $employeeFamilyRepo;

    public $employeeEmergencyRepo;

    public function __construct(
        EmployeeRepository $employeeRepo,
        PositionRepository $positionRepo,
        EmployeeFamilyRepository $employeeFamilyRepo,
        EmployeeEmergencyContactRepository $employeeEmergencyRepo
    )
    {
        $this->employeeRepo = $employeeRepo;
        $this->positionRepo = $positionRepo;
        $this->employeeFamilyRepo = $employeeFamilyRepo;
        $this->employeeEmergencyRepo = $employeeEmergencyRepo;
    }
}