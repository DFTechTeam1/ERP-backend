<?php

namespace Modules\Hrd\Data\Employee;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class BulkSyncEmployeeData extends Data
{
    public function __construct(
        /** @var DataCollection<int, SyncEmployeeItemData> */
        #[DataCollectionOf(SyncEmployeeItemData::class)]
        #[Min(1)]
        public DataCollection $employees,
    ) {}
}
