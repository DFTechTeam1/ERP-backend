<?php

use App\Enums\Accounting\NormalBalance;
use Modules\Finance\Database\Seeders\AccountSeeder;
use Modules\Finance\Models\Account;

/**
 * Verifies the Chart of Accounts seeded from docs/coa_dfactory.xlsx: hierarchy,
 * derived normal_balance/level, and idempotency.
 */
it('seeds the full chart of accounts with hierarchy and derived fields', function () {
    $this->seed(AccountSeeder::class);

    // Group header: not postable, no normal_balance, level 0.
    $kas = Account::where('code', '100')->first();
    expect($kas)->not->toBeNull()
        ->and($kas->is_postable)->toBeFalse()
        ->and($kas->normal_balance)->toBeNull()
        ->and($kas->level)->toBe(0)
        ->and($kas->account_type->value)->toBe('asset');

    // Leaf under the header: postable, parented, level 1, debit, cash-equivalent.
    $kasLeaf = Account::where('code', '101')->first();
    expect($kasLeaf->is_postable)->toBeTrue()
        ->and($kasLeaf->parent_id)->toBe($kas->id)
        ->and($kasLeaf->level)->toBe(1)
        ->and($kasLeaf->normal_balance)->toBe(NormalBalance::Debit)
        ->and($kasLeaf->is_cash_equivalent)->toBeTrue();

    // Contra asset (accumulated depreciation) flips to credit.
    expect(Account::where('code', '211')->first()->normal_balance)->toBe(NormalBalance::Credit);

    // Liability leaf is credit.
    expect(Account::where('code', '311')->first()->normal_balance)->toBe(NormalBalance::Credit);

    // Other income nested under the 800 header keeps its own revenue type + credit balance.
    $header800 = Account::where('code', '800')->first();
    $otherIncome = Account::where('code', '801')->first();
    expect($otherIncome->account_type->value)->toBe('revenue')
        ->and($otherIncome->normal_balance)->toBe(NormalBalance::Credit)
        ->and($otherIncome->parent_id)->toBe($header800->id);

    // Decimal code is preserved.
    expect(Account::where('code', '763.1')->exists())->toBeTrue();

    expect(Account::count())->toBe(145);
});

it('is idempotent (re-seeding does not duplicate)', function () {
    $this->seed(AccountSeeder::class);
    $count = Account::count();

    $this->seed(AccountSeeder::class);

    expect(Account::count())->toBe($count);
});
