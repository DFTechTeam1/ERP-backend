<?php

namespace Modules\Email\Data\Employees\Mutation;

use Modules\Email\Data\BaseData;

final class EmployeeData extends BaseData
{
    public function __construct(
        public ?string $employeeName,
        public ?string $oldPosition,
        public ?string $newPosition,
        public ?string $department,
        public ?string $effectiveDate
    ) {}

}
