<?php

namespace App\Data\Finance\ProjectCost;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class DetailProjectCostData extends Data
{
    public function __construct(
        public readonly string $uid,
        public readonly string $name,
        public readonly string $project_date,
        public readonly string $event_class,
        public readonly string $event_class_color,
        public readonly string $venue,
        public readonly string $pic,
        public readonly string $currency,
        public readonly float $total_cost,
        public readonly float $fix_price,
        public readonly float $amount_paid,
        public readonly float $outstanding,
        public readonly bool $is_fully_paid,
        public readonly string $payment_status,
        public readonly string $fix_price_updated_at,
        public readonly float $gross_profit,
        public readonly float $gross_margin,
        #[DataCollectionOf(DashboardCostCompositionData::class)]
        public readonly array $cost_breakdown,
        public readonly EmployeeRewardData $employee_rewards
    ) {}
}
