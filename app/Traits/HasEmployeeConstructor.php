<?php

namespace App\Traits;

use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Hrd\Services\EmployeeRepoGroup;

trait HasEmployeeConstructor
{
    public $employeeGroupRepo;

    public function getListOfRepository()
    {
        $employeeRepo = new EmployeeRepository();
        $this->employeeGroupRepo = new EmployeeRepoGroup(
            $employeeRepo
        );
    }
}
