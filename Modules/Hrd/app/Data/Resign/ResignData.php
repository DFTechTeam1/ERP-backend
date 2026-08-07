<?php

namespace Modules\Hrd\Data\Resign;

use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\DateFormat;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class ResignData extends Data
{
    public function __construct(
        #[Required, StringType]
        public string $resign_reason_code,
        #[Required, DateFormat('Y-m-d')]
        public string $resign_date,
        #[Nullable, StringType]
        public ?string $remark = null,
        #[BooleanType]
        public bool $sync_greatday = false,
    ) {}
}
