<?php

namespace App\Data\Production;

use App\Data\Production\Cost\EmployeeRewardListData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class CostEstimationListData extends Data
{
    public function __construct(
        public readonly string $project_id,
        public readonly string $client_portal,
        public readonly string $project_name,
        public readonly string $event_date, // d F Y format
        public readonly string $venue,
        public readonly int $total_employees,
        public readonly array $meal_allowances,
        public readonly array $transport_allowances,
        public readonly float $project_price,
        #[DataCollectionOf(EmployeeRewardListData::class)]
        public readonly array $employee_rewards
    ) {}
}
