<?php

namespace Modules\Email\Data\Employees\Mutation;

use Modules\Email\Data\BaseData;

final class SupervisorData extends BaseData {
    public function __construct(
        public string | null $supervisorName,
        public string | null $employeeName,
        public string | null $oldPosition,
        public string | null $newPosition,
        public string | null $department,
        public string | null $effectiveDate
    ) {}
}
