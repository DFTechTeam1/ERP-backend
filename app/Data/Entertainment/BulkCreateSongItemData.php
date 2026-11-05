<?php

namespace App\Data\Entertainment;

use Spatie\LaravelData\Data;

class BulkCreateSongItemData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly array $songs
    ) {}

    public static function rules(): array
    {
        return [
            'name' => 'required|string',
            'songs' => 'required|array',
            'songs.*' => 'string',
        ];
    }
}
