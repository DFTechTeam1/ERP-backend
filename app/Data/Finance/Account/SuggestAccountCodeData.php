<?php

namespace App\Data\Finance\Account;

use App\Enums\Accounting\AccountType;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * Query input for the "suggest next account code" helper. Provide a parent_uid
 * (the suggestion continues that parent's children) and/or an account_type (used
 * for a top-level suggestion when no parent is given).
 */
class SuggestAccountCodeData extends Data
{
    public function __construct(
        public readonly ?string $parent_uid,
        public readonly ?AccountType $account_type,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'parent_uid' => ['nullable', 'string'],
            'account_type' => ['nullable', 'required_without:parent_uid', Rule::enum(AccountType::class)],
        ];
    }
}
