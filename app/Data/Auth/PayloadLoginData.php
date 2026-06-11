<?php

namespace App\Data\Auth;

use Spatie\LaravelData\Data;

class PayloadLoginData extends Data
{
    public function __construct(
        public string $email,
        public string $password,
        public ?int $remember = 0
    ) {}
}
