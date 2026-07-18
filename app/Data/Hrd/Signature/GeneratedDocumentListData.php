<?php

namespace App\Data\Hrd\Signature;

use App\Data\Hrd\Signer\DetailDocumentSignEmployeeData;
use Spatie\LaravelData\Data;

class GeneratedDocumentListData extends Data
{
    public function __construct(
        public readonly string $uid,
        public readonly string $document_name,
        public readonly string $version,
        public readonly string $type,
        public readonly string $status,
        public readonly DetailDocumentSignEmployeeData $employee,
        /** @var array<string, string> */
        public readonly array $signers
    ) {}
}
