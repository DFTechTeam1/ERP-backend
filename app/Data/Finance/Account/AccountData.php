<?php

namespace App\Data\Finance\Account;

use App\Enums\Accounting\AccountSubtype;
use App\Enums\Accounting\AccountType;
use App\Enums\Accounting\CashFlowCategory;
use App\Enums\Accounting\NormalBalance;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * Chart of Accounts resource (response shape, camelCase). `children` is only
 * present in the tree view; pass {@see Optional} to omit it from the payload.
 */
class AccountData extends Data
{
    /**
     * @param  array<int, AccountData>|Optional  $children
     */
    public function __construct(
        public readonly string $uid,
        public readonly string $code,
        public readonly string $name,
        public readonly AccountType $accountType,
        public readonly ?AccountSubtype $accountSubtype,
        public readonly ?NormalBalance $normalBalance,
        public readonly ?string $parentUid,
        public readonly int $level,
        public readonly bool $isPostable,
        public readonly bool $isActive,
        public readonly bool $isLocked,
        public readonly bool $hasTransactions,
        public readonly ?bool $isCashEquivalent,
        public readonly ?CashFlowCategory $cashFlowCategory,
        public readonly int $balance,
        public readonly array|Optional $children,
    ) {}
}
