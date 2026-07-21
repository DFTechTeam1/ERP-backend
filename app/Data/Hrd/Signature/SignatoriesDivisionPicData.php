<?php

namespace App\Data\Hrd\Signature;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class SignatoriesDivisionPicData extends Data
{
    public function __construct(
        public readonly string $uid,
        public readonly string $division_name,
        public readonly string $division_code,
        public readonly string $division_uid,
        public readonly int $headcount,
        public readonly ?SelectedOrgSignatureSignerData $pic,
        public readonly ?SelectedOrgSignatureSignerData $delegate,
        #[DataCollectionOf(SelectedOrgSignatureSignerData::class)]
        public readonly array $signer_options
    ) {}
}
