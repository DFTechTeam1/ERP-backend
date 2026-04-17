<?php

namespace Modules\Email\Data\Employees\Mutation;

use Modules\Email\Data\BaseData;

final class EmployeeData extends BaseData {
    public function __construct(
        public string | null $employeeName,
        public string | null $oldPosition,
        public string | null $newPosition,
        public string | null $department,
        public string | null $effectiveDate
    ) {}

    public function toArray(): array
    {
        return parent::toArray();
    }
}
