<?php

namespace App\Data\Production\Lead\DealChange;

use Spatie\LaravelData\Data;

class ListDealChangesData extends Data
{
    public function __construct(
        public readonly string $uid,
        public readonly string $project_deal_uid,
        public readonly string $project_deal_id,
        public readonly string $event_name,
        public readonly string $project_date,
        public readonly string $request_by,
        public readonly string $requested_at,
        public readonly string $status,
        public readonly array $detail_changes,
        public readonly ?string $approved_at,
        public readonly ?string $rejected_at,
        public readonly bool $can_approve,
        public readonly bool $can_reject,
        public readonly bool $can_delete,
    ) {}
}
