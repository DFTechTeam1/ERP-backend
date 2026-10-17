<?php

namespace App\Data\Finance\AiCost;

use Spatie\LaravelData\Data;

class TopupListData extends Data
{
    public function __construct(
        public readonly string $uid,
        public readonly float $amount_usd,
        public readonly float $amount_idr,
        public readonly string $topup_date,
        public readonly ?string $reference,
        public readonly ?string $note,
        public readonly ?string $proof_of_payment_url,
        public readonly TopupCreatorData $creator,
        public readonly string $created_at
    ) {}
}
