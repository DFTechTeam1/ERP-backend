<?php

namespace App\Data\Finance\AiCost;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;

class TopupTokenData extends Data
{
    public function __construct(
        public readonly float $amount_usd,
        public readonly string $topup_date,
        public readonly ?string $reference,
        public readonly ?string $note,
        public readonly ?UploadedFile $proof_of_payment
    ) {}
}
