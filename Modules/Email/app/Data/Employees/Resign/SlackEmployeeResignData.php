<?php

namespace Modules\Email\Data\Employees\Resign;

use Modules\Email\Data\BaseData;

final class SlackEmployeeResignData extends BaseData
{
    public function __construct(
        public bool $success,
        public string $employeeName,
        public string $employeeEmail,
        public ?string $errorMessage = null
    ) {}
}
