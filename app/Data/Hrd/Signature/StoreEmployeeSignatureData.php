<?php

namespace App\Data\Hrd\Signature;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Attributes\Validation\Image;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;

class StoreEmployeeSignatureData extends Data
{
    public function __construct(
        #[Image]
        #[Max(2048)]
        public UploadedFile $signature,
    ) {}
}
