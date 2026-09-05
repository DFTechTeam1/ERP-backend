<?php

namespace App\Data\Company\ProjectClass;

use Spatie\LaravelData\Data;

class UpdateStatusData extends Data
{
    public function __construct(
        public readonly bool $status
    ) {}
}
