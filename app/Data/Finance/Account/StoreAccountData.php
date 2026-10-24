<?php

namespace App\Data\Finance\Account;

use App\Enums\Accounting\AccountSubtype;
use App\Enums\Accounting\AccountType;
use App\Enums\Accounting\CashFlowCategory;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

class StoreAccountData extends Data
{
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly AccountType $account_type,
        public readonly ?AccountSubtype $account_subtype,
        public readonly ?string $parent_uid,
        public readonly ?bool $is_postable,
        public readonly ?bool $is_cash_equivalent,
        public readonly ?CashFlowCategory $cash_flow_category,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('accounts', 'code')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:150'],
            'account_type' => ['required', Rule::enum(AccountType::class)],
            'account_subtype' => ['nullable', Rule::enum(AccountSubtype::class)],
            'parent_uid' => ['nullable', 'string'],
            'is_postable' => ['nullable', 'boolean'],
            'is_cash_equivalent' => ['nullable', 'boolean'],
            'cash_flow_category' => ['nullable', Rule::enum(CashFlowCategory::class)],
            // Derived / system fields are never client-writable (§4.B / §5).
            'normal_balance' => ['prohibited'],
            'level' => ['prohibited'],
            'is_locked' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'code.unique' => 'This account code already exists.',
        ];
    }
}
