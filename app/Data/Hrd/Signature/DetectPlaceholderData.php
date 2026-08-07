<?php

namespace App\Data\Hrd\Signature;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Attributes\Validation\File;
use Spatie\LaravelData\Data;

class DetectPlaceholderData extends Data
{
    public function __construct(
        #[File]
        public UploadedFile $file,
        public string $documentTypeId
    ) {}
}
