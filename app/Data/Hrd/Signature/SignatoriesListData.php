<?php

namespace App\Data\Hrd\Signature;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class SignatoriesListData extends Data
{
    public function __construct(
        #[DataCollectionOf(OrgSignatoriesListData::class)]
        public readonly array $org_singatories,
        #[DataCollectionOf(SignatoriesDivisionPicData::class)]
        public readonly array $division_pics,
        #[DataCollectionOf(SelectedOrgSignatureSignerData::class)]
        public readonly array $signer_options
    ) {}
}
