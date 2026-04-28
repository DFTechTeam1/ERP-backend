<?php

namespace Modules\Hrd\Data\TransferHistory;

use Spatie\LaravelData\Data;

class ValidEmployeeData extends Data {
    public function __construct(
        public int $employeeId,
        public string $employeeName,
        public string $reason
    ) {}
}