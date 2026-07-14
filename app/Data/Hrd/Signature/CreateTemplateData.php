<?php

namespace App\Data\Hrd\Signature;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\File;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class CreateTemplateData extends Data
{
    public function __construct(
        public string $name,
        #[Exists(table: 'document_types', column: 'id')]
        public string $document_type_id,
        #[File]
        #[Min(1)]
        public UploadedFile $file,
        /** @var array<int, string> */
        public array $placeholders
    ) {}
}
