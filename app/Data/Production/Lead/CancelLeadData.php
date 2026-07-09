<?php

namespace App\Data\Production\Lead;

use Spatie\LaravelData\Data;

class CancelLeadData extends Data
{
    public function __construct(
        public readonly string $reason
    ) {}
}
