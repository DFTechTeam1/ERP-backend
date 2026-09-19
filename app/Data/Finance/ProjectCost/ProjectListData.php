<?php

namespace App\Data\Finance\ProjectCost;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class ProjectListData extends Data
{
    public function __construct(
        public readonly int $totalData,
        #[DataCollectionOf(ProjectItemData::class)]
        public readonly array $paginated
    ) {}
}
