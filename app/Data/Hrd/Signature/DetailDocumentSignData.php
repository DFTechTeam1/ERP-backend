<?php

namespace App\Data\Hrd\Signature;

use App\Data\Hrd\Signer\DetailDocumentSignEmployeeData;
use App\Data\Hrd\Signer\DetailDocumentSignSignersData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class DetailDocumentSignData extends Data
{
    public function __construct(
        public readonly string $uid,
        public readonly string $template_uid,
        public readonly string $version_id,
        public readonly string $document_name,
        public readonly string $version,
        public readonly string $type,
        public readonly string $status,
        public readonly DetailDocumentSignEmployeeData $employee,
        #[DataCollectionOf(DetailDocumentSignSignersData::class)]
        public readonly array $signers
    ) {}
}
