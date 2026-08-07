<?php

namespace App\Data\Hrd\Signature;

use Spatie\LaravelData\Data;

class MyGeneratedDocumentListData extends Data
{
    public function __construct(
        public readonly string $uid,
        public readonly string $document_name,
        public readonly string $version,
        public readonly string $type,
        /** @var array<int, MyDocumentSignerData> */
        public readonly array $signers,
    ) {}
}
