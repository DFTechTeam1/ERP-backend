<?php

namespace App\Data\Production\Entertainment;

use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class CreateJumpBackData extends Data
{
    public function __construct(
        /** @var array<int, string> */
        #[Min(1)]
        public array $assignee_uids,
        public string $due,
        public string $name,
        public ?string $note,
        /** @var array<int, string> */
        #[Min(1)]
        public array $song_uids
    ) {}
}
