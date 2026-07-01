<?php

namespace App\Data\Whatsapp;

use Spatie\LaravelData\Data;

class WhatsappLogData extends Data
{
    public function __construct(
        public readonly string $to,
        public readonly string $text,
        public readonly string $service_type,
        public readonly string $action_type,
        public readonly mixed $response,
        public readonly string $created_at
    ) {}
}
