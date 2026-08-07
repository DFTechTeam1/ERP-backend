<?php

namespace App\Data\Hrd\Signature;

use Spatie\LaravelData\Attributes\Validation\Digits;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class ValidateOtpData extends Data
{
    public function __construct(
        #[Required]
        #[Digits(6)]
        public readonly string $otp
    ) {}
}
