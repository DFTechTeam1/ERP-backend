<?php

namespace App\Data\Finance\ProjectCost;

use Spatie\LaravelData\Data;

class ProjectItemData extends Data
{
    public function __construct(
        public readonly string $uid,
        public readonly string $name,
        public readonly string $project_date,
        public readonly string $event_class,
        public readonly string $event_class_color,
        public readonly string $venue,
        public readonly string $pic,
        public readonly string $status,
        public readonly string $status_color,
        public readonly float $total_cost,
        public readonly float $total_employee_reward,
        public readonly float $fix_price,
        public readonly float $amount_paid,
        public readonly bool $is_fully_paid,
        public readonly ?string $fix_price_updated_at,
        public readonly string $currency
    ) {}
}
