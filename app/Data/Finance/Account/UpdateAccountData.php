<?php

namespace App\Data\Finance\Account;

use App\Enums\Accounting\AccountSubtype;
use App\Enums\Accounting\AccountType;
use App\Enums\Accounting\CashFlowCategory;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * PATCH payload — send only the fields that changed. Anything left out stays an
 * {@see Optional} and is not touched. Per-field editability (§4.B) and the
 * code/type transaction+lock guards are enforced in the service.
 */
class UpdateAccountData extends Data
{
    public function __construct(
        public readonly string|Optional $name,
        public readonly string|Optional $code,
        public readonly AccountType|Optional $account_type,
        public readonly AccountSubtype|null|Optional $account_subtype,
        public readonly bool|Optional $is_active,
        public readonly bool|null|Optional $is_cash_equivalent,
        public readonly CashFlowCategory|null|Optional $cash_flow_category,
        public readonly bool|Optional $is_postable,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'code' => ['sometimes', 'string', 'max:20', Rule::unique('accounts', 'code')->ignore(request()->route('uid'), 'uid')],
            'account_type' => ['sometimes', Rule::enum(AccountType::class)],
            'account_subtype' => ['sometimes', 'nullable', Rule::enum(AccountSubtype::class)],
            'is_active' => ['sometimes', 'boolean'],
            'is_cash_equivalent' => ['sometimes', 'nullable', 'boolean'],
            'cash_flow_category' => ['sometimes', 'nullable', Rule::enum(CashFlowCategory::class)],
            'is_postable' => ['sometimes', 'boolean'],
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
