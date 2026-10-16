<?php

namespace App\Data\User\AfterEffect;

use Spatie\LaravelData\Data;

class LoginHistoryListData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $uid,
        public readonly string $user_name,
        public readonly string $user_email,
        public readonly bool $login_successful,
        public readonly ?string $failure_reason,
        public readonly string $ip_address,
        public readonly string $ae_version,
        public readonly string $os,
        public readonly ?string $login_at,
        public readonly bool $token_active,
        public readonly ?string $token_expires_at,
    ) {}
}
