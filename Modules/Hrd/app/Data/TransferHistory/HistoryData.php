<?php

namespace Modules\Hrd\Data\TransferHistory;

use Spatie\LaravelData\Data;

class HistoryData extends Data {
    public function __construct(
        /** @var array<int, ValidEmployeeData[]> */
        public array $validData,

        /** @var array<int, ValidEmployeeData[]> */
        public array $failedData
    ) {}
}