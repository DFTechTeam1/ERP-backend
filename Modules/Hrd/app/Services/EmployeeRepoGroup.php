<?php

namespace Modules\Hrd\Services;

use Modules\Hrd\Repository\EmployeeRepository;

class EmployeeRepoGroup {
    public $employeeRepo;

    public function __construct(
        EmployeeRepository $employeeRepo
    )
    {
        $this->employeeRepo = $employeeRepo;
    }
}