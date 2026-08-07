<?php

namespace App\Data\Hrd\Signature;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class OrgSignatoriesListData extends Data
{
    public function __construct(
        public readonly string $role_key,
        public readonly string $role_label,
        public readonly ?string $description,
        public readonly ?SelectedOrgSignatureSignerData $signer,
        #[DataCollectionOf(SelectedOrgSignatureSignerData::class)]
        public readonly array $signer_options,
    ) {}
}
