<?php

namespace App\Traits;

use App\Repository\RoleRepository;
use App\Repository\UserLoginHistoryRepository;
use App\Repository\UserRepository;
use App\Services\GeneralService;
use App\Services\RoleService;
use App\Services\UserService;
use Modules\Company\Repository\PositionRepository;
use Modules\Hrd\Repository\EmployeeEmergencyContactRepository;
use Modules\Hrd\Repository\EmployeeFamilyRepository;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Hrd\Services\EmployeeRepoGroup;
use Modules\Hrd\Services\EmployeeService;
use Modules\Production\Repository\ProjectPersonInChargeRepository;
use Modules\Production\Repository\ProjectRepository;
use Modules\Production\Repository\ProjectTaskPicHistoryRepository;
use Modules\Production\Repository\ProjectTaskRepository;
use Modules\Production\Repository\ProjectVjRepository;

trait HasEmployeeConstructor
{
    public $employeeGroupRepo;

    public $employeeService;

    public function setConstructor(
        $employeeRepo = null,
        $positionRepo = null,
        $userRepo = null,
        $projectTaskRepo = null,
        $projectRepo = null,
        $projectVjRepo = null,
        $projectPicRepo = null,
        $projectTaskPicHistoryRepo = null,
        $employeeFamilyRepo = null,
        $employeeEmergencyRepo = null,
        $userService = null,
        $generalService =  null
    )
    {
        $userServiceData = new UserService(
            new UserRepository,
            new EmployeeRepository,
            new RoleRepository,
            new UserLoginHistoryRepository,
            new GeneralService,
            new RoleService
        );
        if ($userService) {
            $userServiceData = $userService;
        }

        $this->employeeService = new EmployeeService(
            $employeeRepo ? $employeeRepo : new EmployeeRepository,
            $positionRepo ? $positionRepo : new PositionRepository,
            $userRepo ? $userRepo : new UserRepository,
            $projectTaskRepo ? $projectTaskRepo : new ProjectTaskRepository,
            $projectRepo ? $projectRepo : new ProjectRepository,
            $projectVjRepo ? $projectVjRepo : new ProjectVjRepository,
            $projectPicRepo ? $projectPicRepo : new ProjectPersonInChargeRepository,
            $projectTaskPicHistoryRepo ? $projectTaskPicHistoryRepo : new ProjectTaskPicHistoryRepository,
            $employeeFamilyRepo ? $employeeFamilyRepo : new EmployeeFamilyRepository,
            $employeeEmergencyRepo ? $employeeEmergencyRepo : new EmployeeEmergencyContactRepository,
            $userService ? $userService : $userServiceData,
            $generalService ? $generalService : new GeneralService
        );
    }
}
