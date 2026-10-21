<?php

namespace App\Data\Finance\Account;

use Spatie\LaravelData\Data;

class MoveAccountData extends Data
{
    public function __construct(
        public readonly ?string $parent_uid,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'parent_uid' => ['present', 'nullable', 'string'],
        ];
    }
}
