<?php

namespace App\Data\Hrd\Signature;

use Spatie\LaravelData\Data;

class AssignSignatoriesData extends Data
{
    public function __construct(
        public readonly string $pic_uid,
        public readonly ?string $delegate_uid
    ) {}
}
