<?php

namespace Modules\Hrd\Data\TransferHistory;

use Spatie\LaravelData\Data;

class HistoryData extends Data {
    public function __construct(
        /** @var array<string, ValidEmployeeData[]> */
        public array $validData,

        /** @var array<string, ValidEmployeeData[]> */
        public array $failedData
    ) {}
}