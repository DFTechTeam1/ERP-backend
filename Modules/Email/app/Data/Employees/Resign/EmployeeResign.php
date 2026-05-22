<?php

namespace Modules\Email\Data\Employees\Resign;

use Modules\Email\Data\BaseData;

final class EmployeeResign extends BaseData
{
    public function __construct(
        public string $employeeName,
        public string $employeeId,
        public string $position,
        public string $department,
        public string $resignDate,
    ) {}
}
