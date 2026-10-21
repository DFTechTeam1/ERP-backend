<?php

use App\Data\Finance\Account\MoveAccountData;
use App\Data\Finance\Account\StoreAccountData;
use App\Data\Finance\Account\SuggestAccountCodeData;
use App\Data\Finance\Account\UpdateAccountData;
use App\Enums\Accounting\AccountType;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Exceptions\Accounting\AccountRuleException;
use Modules\Finance\Models\Account;
use Modules\Finance\Repository\AccountRepository;
use Modules\Finance\Services\AccountService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertSoftDeleted;

/**
 * Chart of Accounts (Bagan Akun) — AccountService + the /api/accounting/chart-of-accounts
 * endpoints. Implements docs/chart-of-accounts-api-contract.md: tree/flat listing, CRUD,
 * reparent, activate/deactivate, the editability matrix (§4), normal_balance derivation (§5)
 * and the error-code catalog (§6). balance/has_transactions are stubbed (no ledger yet).
 */
function coaService(): AccountService
{
    return app(AccountService::class);
}

/**
 * Persist an account directly (uid via ModelObserver, created_by via ModelCreationObserver).
 */
function coaMake(array $attrs = []): Account
{
    return Account::create(array_merge([
        'code' => 'C-'.uniqid(),
        'name' => 'Acct '.uniqid(),
        'account_type' => AccountType::Asset->value,
        'account_subtype' => null,
        'normal_balance' => null,
        'parent_id' => null,
        'level' => 0,
        'is_postable' => true,
        'is_cash_equivalent' => false,
        'cash_flow_category' => null,
        'is_active' => true,
        'is_locked' => false,
    ], $attrs));
}

/**
 * Authenticate a user holding the given coa permissions (sanctum guard).
 */
function coaActor(array $permissions = ['list_coa', 'create_coa', 'update_coa', 'delete_coa']): User
{
    $user = User::factory()->create();

    foreach (['list_coa', 'create_coa', 'update_coa', 'delete_coa'] as $slug) {
        Permission::firstOrCreate(['name' => $slug, 'guard_name' => 'sanctum']);
    }
    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    actingAs($user, 'sanctum');

    return $user;
}

/**
 * A partial-mock of AccountService with the ledger stubs overridden, so the
 * transaction/balance-gated rule branches (which the real 0/false stubs make
 * unreachable) can be exercised today.
 */
function coaServiceWithLedger(bool $hasTransactions = true, int $balance = 0): AccountService
{
    /** @var AccountService $service */
    $service = Mockery::mock(AccountService::class, [app(AccountRepository::class)])
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('hasTransactions')->andReturn($hasTransactions);
    $service->shouldReceive('balance')->andReturn($balance);

    return $service;
}

/**
 * Assert that $fn throws an AccountRuleException rendering the given status + error_code.
 */
function assertCoaError(callable $fn, int $status, string $errorCode): void
{
    try {
        $fn();
        test()->fail("Expected AccountRuleException with error_code {$errorCode}, none thrown");
    } catch (AccountRuleException $e) {
        $rendered = $e->render(request());
        expect($rendered->getStatusCode())->toBe($status)
            ->and($rendered->getData(true)['error_code'] ?? null)->toBe($errorCode);
    }
}

// ---- store ---------------------------------------------------------------

describe('store', function () {
    it('creates a top-level header with null subtype/normal_balance and level 0 (service)', function () {
        $response = coaService()->store(StoreAccountData::from([
            'code' => '1-000',
            'name' => 'Assets',
            'account_type' => 'asset',
            'account_subtype' => null,
            'parent_uid' => null,
            'is_postable' => false,
            'is_cash_equivalent' => null,
            'cash_flow_category' => null,
        ]));

        expect($response['error'])->toBeFalse()
            ->and($response['data']['accountType'])->toBe('asset')
            ->and($response['data']['normalBalance'])->toBeNull()
            ->and($response['data']['accountSubtype'])->toBeNull()
            ->and($response['data']['level'])->toBe(0)
            ->and($response['data']['isPostable'])->toBeFalse();

        assertDatabaseHas('accounts', ['code' => '1-000', 'normal_balance' => null, 'level' => 0, 'is_postable' => false]);
    });

    it('creates a postable leaf under a header, deriving a debit normal_balance and level 1 (service)', function () {
        $header = coaMake(['code' => '1-100', 'name' => 'Current Assets', 'is_postable' => false]);

        $response = coaService()->store(StoreAccountData::from([
            'code' => '1-1001',
            'name' => 'Cash on Hand',
            'account_type' => 'asset',
            'account_subtype' => 'cash_and_bank',
            'parent_uid' => $header->uid,
            'is_postable' => true,
            'is_cash_equivalent' => true,
            'cash_flow_category' => 'operating',
        ]));

        expect($response['data']['normalBalance'])->toBe('debit')
            ->and($response['data']['level'])->toBe(1)
            ->and($response['data']['parentUid'])->toBe($header->uid)
            ->and($response['data']['isCashEquivalent'])->toBeTrue();

        assertDatabaseHas('accounts', ['code' => '1-1001', 'normal_balance' => 'debit', 'level' => 1]);
    });

    it('derives a credit normal_balance for a contra asset (accumulated depreciation) (service)', function () {
        $response = coaService()->store(StoreAccountData::from([
            'code' => '1-2099',
            'name' => 'Accumulated Depreciation',
            'account_type' => 'asset',
            'account_subtype' => 'accumulated_depreciation',
            'parent_uid' => null,
            'is_postable' => true,
            'is_cash_equivalent' => null,
            'cash_flow_category' => null,
        ]));

        expect($response['data']['normalBalance'])->toBe('credit');
    });

    it('derives credit for liabilities and debit for owner drawing (service)', function () {
        $service = coaService();

        $liability = $service->store(StoreAccountData::from([
            'code' => '2-1000', 'name' => 'Accounts Payable', 'account_type' => 'liability',
            'account_subtype' => 'accounts_payable', 'parent_uid' => null, 'is_postable' => true,
            'is_cash_equivalent' => null, 'cash_flow_category' => null,
        ]));
        $drawing = $service->store(StoreAccountData::from([
            'code' => '3-9000', 'name' => 'Owner Drawing', 'account_type' => 'equity',
            'account_subtype' => 'owner_drawing', 'parent_uid' => null, 'is_postable' => true,
            'is_cash_equivalent' => null, 'cash_flow_category' => null,
        ]));

        expect($liability['data']['normalBalance'])->toBe('credit')
            ->and($drawing['data']['normalBalance'])->toBe('debit');
    });

    it('rejects a parent that does not exist / is postable / has a different type (service)', function () {
        $missing = fn () => coaService()->store(StoreAccountData::from([
            'code' => 'X1', 'name' => 'X', 'account_type' => 'asset', 'account_subtype' => null,
            'parent_uid' => 'does-not-exist', 'is_postable' => true, 'is_cash_equivalent' => null, 'cash_flow_category' => null,
        ]));
        expect($missing)->toThrow(ValidationException::class);

        $postableParent = coaMake(['is_postable' => true, 'account_type' => 'asset']);
        $ontoPostable = fn () => coaService()->store(StoreAccountData::from([
            'code' => 'X2', 'name' => 'X', 'account_type' => 'asset', 'account_subtype' => null,
            'parent_uid' => $postableParent->uid, 'is_postable' => true, 'is_cash_equivalent' => null, 'cash_flow_category' => null,
        ]));
        expect($ontoPostable)->toThrow(ValidationException::class);

        $assetHeader = coaMake(['is_postable' => false, 'account_type' => 'asset']);
        $typeMismatch = fn () => coaService()->store(StoreAccountData::from([
            'code' => 'X3', 'name' => 'X', 'account_type' => 'liability', 'account_subtype' => null,
            'parent_uid' => $assetHeader->uid, 'is_postable' => true, 'is_cash_equivalent' => null, 'cash_flow_category' => null,
        ]));
        expect($typeMismatch)->toThrow(ValidationException::class);
    });
});

// ---- detail --------------------------------------------------------------

describe('detail', function () {
    it('returns a single account with its parent uid and no children key (service)', function () {
        $header = coaMake(['code' => '1-000', 'is_postable' => false]);
        $leaf = coaMake(['code' => '1-0001', 'parent_id' => $header->id, 'level' => 1]);

        $response = coaService()->detail($leaf->uid);

        expect($response['error'])->toBeFalse()
            ->and($response['data']['uid'])->toBe($leaf->uid)
            ->and($response['data']['parentUid'])->toBe($header->uid)
            ->and($response['data'])->not->toHaveKey('children');
    });

    it('raises a 404 (no error_code) on an unknown uid (service)', function () {
        try {
            coaService()->detail('nope');
            test()->fail('Expected AccountRuleException');
        } catch (AccountRuleException $e) {
            $rendered = $e->render(request());
            expect($rendered->getStatusCode())->toBe(404)
                ->and($rendered->getData(true))->not->toHaveKey('error_code');
        }
    });
});

// ---- update --------------------------------------------------------------

describe('update', function () {
    it('updates the name of any account (service)', function () {
        $account = coaMake(['name' => 'Old']);

        coaService()->update(UpdateAccountData::from(['name' => 'New Name']), $account->uid);

        assertDatabaseHas('accounts', ['id' => $account->id, 'name' => 'New Name']);
    });

    it('allows a code change on an unlocked, transaction-free account (service)', function () {
        $account = coaMake(['code' => 'OLD-CODE']);

        coaService()->update(UpdateAccountData::from(['code' => 'NEW-CODE']), $account->uid);

        assertDatabaseHas('accounts', ['id' => $account->id, 'code' => 'NEW-CODE']);
    });

    it('rejects a code change on a locked account with 403 ACCOUNT_LOCKED (service)', function () {
        $account = coaMake(['code' => 'LOCKED-1', 'is_locked' => true]);

        assertCoaError(
            fn () => coaService()->update(UpdateAccountData::from(['code' => 'NEW']), $account->uid),
            403,
            'ACCOUNT_LOCKED',
        );
    });

    it('still edits always-allowed fields (name, cash flow) on a locked account (service)', function () {
        $account = coaMake(['code' => 'SYS', 'is_locked' => true, 'name' => 'Old']);

        coaService()->update(UpdateAccountData::from([
            'name' => 'Renamed',
            'is_cash_equivalent' => true,
            'cash_flow_category' => 'operating',
        ]), $account->uid);

        assertDatabaseHas('accounts', ['id' => $account->id, 'name' => 'Renamed', 'is_cash_equivalent' => true, 'cash_flow_category' => 'operating']);
    });

    it('recomputes normal_balance when the subtype changes to a contra subtype (service)', function () {
        $account = coaMake([
            'code' => '1-5000', 'account_type' => 'asset', 'account_subtype' => 'fixed_asset',
            'normal_balance' => 'debit', 'is_postable' => true,
        ]);

        coaService()->update(UpdateAccountData::from(['account_subtype' => 'accumulated_depreciation']), $account->uid);

        assertDatabaseHas('accounts', ['id' => $account->id, 'account_subtype' => 'accumulated_depreciation', 'normal_balance' => 'credit']);
    });

    it('recomputes normal_balance when the account_type changes (service)', function () {
        $account = coaMake([
            'code' => '1-6000', 'account_type' => 'asset', 'account_subtype' => 'other_current_asset',
            'normal_balance' => 'debit', 'is_postable' => true,
        ]);

        coaService()->update(UpdateAccountData::from(['account_type' => 'liability', 'account_subtype' => 'other_current_liability']), $account->uid);

        assertDatabaseHas('accounts', ['id' => $account->id, 'account_type' => 'liability', 'normal_balance' => 'credit']);
    });

    it('turns a childless leaf into a header, clearing its normal_balance (service)', function () {
        $account = coaMake(['code' => '1-7000', 'account_subtype' => 'cash_and_bank', 'normal_balance' => 'debit', 'is_postable' => true]);

        coaService()->update(UpdateAccountData::from(['is_postable' => false]), $account->uid);

        assertDatabaseHas('accounts', ['id' => $account->id, 'is_postable' => false, 'normal_balance' => null]);
    });

    it('refuses to turn a header with children into a leaf with 409 ACCOUNT_HAS_CHILDREN (service)', function () {
        $header = coaMake(['is_postable' => false]);
        coaMake(['parent_id' => $header->id, 'level' => 1]);

        assertCoaError(
            fn () => coaService()->update(UpdateAccountData::from(['is_postable' => true]), $header->uid),
            409,
            'ACCOUNT_HAS_CHILDREN',
        );
    });

    it('rejects a code change once the account has transactions with 409 (service)', function () {
        $account = coaMake(['code' => 'TXN-1']);

        assertCoaError(
            fn () => coaServiceWithLedger(hasTransactions: true)->update(UpdateAccountData::from(['code' => 'TXN-2']), $account->uid),
            409,
            'ACCOUNT_HAS_TRANSACTIONS',
        );
    });

    it('refuses to make a leaf into a header once it has transactions with 409 LEAF_TO_HEADER_WITH_TXNS (service)', function () {
        $account = coaMake(['code' => 'TXN-LEAF', 'is_postable' => true]);

        assertCoaError(
            fn () => coaServiceWithLedger(hasTransactions: true)->update(UpdateAccountData::from(['is_postable' => false]), $account->uid),
            409,
            'LEAF_TO_HEADER_WITH_TXNS',
        );
    });
});

// ---- move ----------------------------------------------------------------

describe('move', function () {
    it('moves an account to the top level and recomputes descendant levels (service)', function () {
        $root = coaMake(['code' => 'A', 'is_postable' => false, 'level' => 0]);
        $mid = coaMake(['code' => 'A-1', 'is_postable' => false, 'parent_id' => $root->id, 'level' => 1]);
        $leaf = coaMake(['code' => 'A-1-1', 'parent_id' => $mid->id, 'level' => 2]);

        coaService()->move(MoveAccountData::from(['parent_uid' => null]), $mid->uid);

        assertDatabaseHas('accounts', ['id' => $mid->id, 'parent_id' => null, 'level' => 0]);
        assertDatabaseHas('accounts', ['id' => $leaf->id, 'level' => 1]);
    });

    it('reparents a leaf under another header of the same type (service)', function () {
        $from = coaMake(['code' => 'F', 'is_postable' => false, 'account_type' => 'asset']);
        $to = coaMake(['code' => 'T', 'is_postable' => false, 'account_type' => 'asset']);
        $leaf = coaMake(['code' => 'F-1', 'account_type' => 'asset', 'parent_id' => $from->id, 'level' => 1]);

        $response = coaService()->move(MoveAccountData::from(['parent_uid' => $to->uid]), $leaf->uid);

        expect($response['data']['parentUid'])->toBe($to->uid)
            ->and($response['data']['level'])->toBe(1);
        assertDatabaseHas('accounts', ['id' => $leaf->id, 'parent_id' => $to->id, 'level' => 1]);
    });

    it('rejects a reparent onto a different type, a postable parent, or a cycle with the right codes (service)', function () {
        $assetHeader = coaMake(['code' => 'AH', 'is_postable' => false, 'account_type' => 'asset']);
        $liabilityHeader = coaMake(['code' => 'LH', 'is_postable' => false, 'account_type' => 'liability']);
        $assetLeaf = coaMake(['code' => 'AL', 'account_type' => 'asset', 'parent_id' => $assetHeader->id, 'level' => 1]);
        $assetSubHeader = coaMake(['code' => 'ASH', 'is_postable' => false, 'account_type' => 'asset', 'parent_id' => $assetHeader->id, 'level' => 1]);
        $postable = coaMake(['code' => 'PP', 'is_postable' => true, 'account_type' => 'asset']);

        assertCoaError(fn () => coaService()->move(MoveAccountData::from(['parent_uid' => $liabilityHeader->uid]), $assetLeaf->uid), 422, 'REPARENT_TYPE_MISMATCH');
        assertCoaError(fn () => coaService()->move(MoveAccountData::from(['parent_uid' => $postable->uid]), $assetLeaf->uid), 422, 'REPARENT_ONTO_POSTABLE');
        // cycle: move the header under its own (header) descendant
        assertCoaError(fn () => coaService()->move(MoveAccountData::from(['parent_uid' => $assetSubHeader->uid]), $assetHeader->uid), 422, 'REPARENT_CYCLE');
    });

    it('rejects a reparent on an unknown new parent with a 422 validation error (service)', function () {
        $leaf = coaMake(['code' => 'ML']);

        expect(fn () => coaService()->move(MoveAccountData::from(['parent_uid' => 'nope']), $leaf->uid))
            ->toThrow(ValidationException::class);
    });

    it('blocks a non-admin reparent once the account has transactions with 409 (service)', function () {
        $header = coaMake(['code' => 'TH', 'is_postable' => false, 'account_type' => 'asset']);
        $leaf = coaMake(['code' => 'TL', 'account_type' => 'asset', 'parent_id' => $header->id, 'level' => 1]);

        // no authenticated admin -> isAdmin() is false
        assertCoaError(
            fn () => coaServiceWithLedger(hasTransactions: true)->move(MoveAccountData::from(['parent_uid' => null]), $leaf->uid),
            409,
            'ACCOUNT_HAS_TRANSACTIONS',
        );
    });
});

// ---- activate / deactivate -----------------------------------------------

describe('activate / deactivate', function () {
    it('deactivates then activates an account (service)', function () {
        $account = coaMake(['is_active' => true]);

        coaService()->deactivate($account->uid);
        assertDatabaseHas('accounts', ['id' => $account->id, 'is_active' => false]);

        coaService()->activate($account->uid);
        assertDatabaseHas('accounts', ['id' => $account->id, 'is_active' => true]);
    });

    it('refuses to deactivate an account that still has a balance with 409 ACCOUNT_HAS_BALANCE (service)', function () {
        $account = coaMake(['is_active' => true, 'is_postable' => true]);

        assertCoaError(
            fn () => coaServiceWithLedger(hasTransactions: false, balance: 500000)->deactivate($account->uid),
            409,
            'ACCOUNT_HAS_BALANCE',
        );
    });
});

// ---- delete --------------------------------------------------------------

describe('delete', function () {
    it('soft-deletes an empty, unlocked account (service)', function () {
        $account = coaMake();

        coaService()->delete($account->uid);

        assertSoftDeleted('accounts', ['id' => $account->id]);
    });

    it('refuses to delete a header that still has children with 409 ACCOUNT_HAS_CHILDREN (service)', function () {
        $header = coaMake(['is_postable' => false]);
        coaMake(['parent_id' => $header->id, 'level' => 1]);

        assertCoaError(fn () => coaService()->delete($header->uid), 409, 'ACCOUNT_HAS_CHILDREN');
    });

    it('refuses to delete a locked account with 403 ACCOUNT_LOCKED (service)', function () {
        $account = coaMake(['is_locked' => true]);

        assertCoaError(fn () => coaService()->delete($account->uid), 403, 'ACCOUNT_LOCKED');
    });

    it('refuses to delete an account that has transactions or a balance with 409 (service)', function () {
        $account = coaMake(['is_postable' => true]);

        assertCoaError(
            fn () => coaServiceWithLedger(hasTransactions: false, balance: 250000)->delete($account->uid),
            409,
            'ACCOUNT_HAS_TRANSACTIONS',
        );
    });

    it('404s on an unknown uid (service)', function () {
        expect(fn () => coaService()->delete('nope'))->toThrow(AccountRuleException::class);
    });
});

// ---- list ----------------------------------------------------------------

describe('list', function () {
    it('returns a nested tree with children (service)', function () {
        $root = coaMake(['code' => '1-000', 'name' => 'Assets', 'is_postable' => false, 'level' => 0]);
        $leaf = coaMake(['code' => '1-0001', 'name' => 'Cash', 'parent_id' => $root->id, 'level' => 1]);

        $response = coaService()->list();

        expect($response['data'])->toHaveCount(1)
            ->and($response['data'][0]['code'])->toBe('1-000')
            ->and($response['data'][0]['children'])->toHaveCount(1)
            ->and($response['data'][0]['children'][0]['code'])->toBe('1-0001');
    });

    it('keeps ancestors of a search match in the tree (service)', function () {
        $root = coaMake(['code' => '1-000', 'name' => 'Assets', 'is_postable' => false, 'level' => 0]);
        coaMake(['code' => '1-0001', 'name' => 'Bank Mandiri', 'parent_id' => $root->id, 'level' => 1]);
        coaMake(['code' => '2-000', 'name' => 'Liabilities', 'is_postable' => false, 'account_type' => 'liability', 'level' => 0]);

        request()->merge(['search' => 'Mandiri']);
        $response = coaService()->list();

        expect($response['data'])->toHaveCount(1)
            ->and($response['data'][0]['code'])->toBe('1-000')
            ->and($response['data'][0]['children'][0]['name'])->toBe('Bank Mandiri');
    });

    it('returns a flat list with no children key when view=flat (service)', function () {
        $root = coaMake(['code' => '1-000', 'is_postable' => false]);
        coaMake(['code' => '1-0001', 'parent_id' => $root->id, 'level' => 1]);

        request()->merge(['view' => 'flat']);
        $response = coaService()->list();

        expect($response['data'])->toHaveCount(2)
            ->and($response['data'][0])->not->toHaveKey('children');
    });

    it('filters a flat list by account type (service)', function () {
        coaMake(['code' => '1-0001', 'account_type' => 'asset']);
        coaMake(['code' => '2-0001', 'account_type' => 'liability']);

        request()->merge(['view' => 'flat', 'type' => 'liability']);
        $response = coaService()->list();

        expect($response['data'])->toHaveCount(1)
            ->and($response['data'][0]['code'])->toBe('2-0001');
    });

    it('filters a flat list by active flag (service)', function () {
        coaMake(['code' => '1-0001', 'is_active' => true]);
        coaMake(['code' => '1-0002', 'is_active' => false]);

        request()->merge(['view' => 'flat', 'active' => 'false']);
        $response = coaService()->list();

        expect($response['data'])->toHaveCount(1)
            ->and($response['data'][0]['code'])->toBe('1-0002');
    });
});

// ---- suggestCode ---------------------------------------------------------

describe('suggestCode', function () {
    it('suggests the next code after the highest sibling under a parent (service)', function () {
        $parent = coaMake(['code' => '1-100', 'is_postable' => false]);
        coaMake(['code' => '1-1001', 'parent_id' => $parent->id, 'level' => 1]);
        coaMake(['code' => '1-1002', 'parent_id' => $parent->id, 'level' => 1]);
        coaMake(['code' => '1-1003', 'parent_id' => $parent->id, 'level' => 1]);

        $response = coaService()->suggestCode(SuggestAccountCodeData::from(['parent_uid' => $parent->uid]));

        expect($response['data']['code'])->toBe('1-1004');
    });

    it('seeds the first child from the parent code when the parent has no children (service)', function () {
        $parent = coaMake(['code' => '1-100', 'is_postable' => false]);

        $response = coaService()->suggestCode(SuggestAccountCodeData::from(['parent_uid' => $parent->uid]));

        expect($response['data']['code'])->toBe('1-1001');
    });

    it('seeds a top-level code from the account type when none exist (service)', function () {
        $response = coaService()->suggestCode(SuggestAccountCodeData::from(['account_type' => 'liability']));

        expect($response['data']['code'])->toBe('2-000');
    });

    it('increments the highest top-level code of the type (service)', function () {
        coaMake(['code' => '1-000', 'account_type' => 'asset', 'is_postable' => false, 'parent_id' => null]);

        $response = coaService()->suggestCode(SuggestAccountCodeData::from(['account_type' => 'asset']));

        expect($response['data']['code'])->toBe('1-001');
    });

    it('skips a code that is already used elsewhere (service)', function () {
        $parentA = coaMake(['code' => '1-100', 'is_postable' => false]);
        coaMake(['code' => '1-1001', 'parent_id' => $parentA->id, 'level' => 1]);
        coaMake(['code' => '1-1002', 'parent_id' => $parentA->id, 'level' => 1]);
        // 1-1003 exists under a different parent, so the suggestion must jump to 1-1004.
        $parentB = coaMake(['code' => '9-900', 'is_postable' => false]);
        coaMake(['code' => '1-1003', 'parent_id' => $parentB->id, 'level' => 1]);

        $response = coaService()->suggestCode(SuggestAccountCodeData::from(['parent_uid' => $parentA->uid]));

        expect($response['data']['code'])->toBe('1-1004');
    });

    it('raises a 422 when the parent uid is unknown (service)', function () {
        expect(fn () => coaService()->suggestCode(SuggestAccountCodeData::from(['parent_uid' => 'nope'])))
            ->toThrow(ValidationException::class);
    });
});

// ---- endpoints (e2e) -----------------------------------------------------

describe('endpoints', function () {
    it('lists the chart via the endpoint (200)', function () {
        coaActor();
        coaMake(['code' => '1-000', 'name' => 'Assets', 'is_postable' => false]);

        $this->getJson('/api/accounting/chart-of-accounts')
            ->assertOk()
            ->assertJsonPath('data.0.code', '1-000');
    });

    it('creates an account via the endpoint (201)', function () {
        coaActor();

        $this->postJson('/api/accounting/chart-of-accounts', [
            'code' => '4-0001',
            'name' => 'Service Revenue',
            'account_type' => 'revenue',
            'account_subtype' => 'operating_revenue',
            'parent_uid' => null,
            'is_postable' => true,
        ])->assertCreated()
            ->assertJsonPath('data.normalBalance', 'credit');

        assertDatabaseHas('accounts', ['code' => '4-0001', 'normal_balance' => 'credit']);
    });

    it('rejects a duplicate code via the endpoint (422 errors.code)', function () {
        coaActor();
        coaMake(['code' => 'DUP-1']);

        $this->postJson('/api/accounting/chart-of-accounts', [
            'code' => 'DUP-1',
            'name' => 'Dup',
            'account_type' => 'asset',
        ])->assertStatus(422)
            ->assertJsonValidationErrorFor('code');
    });

    it('rejects a client-sent normal_balance via the endpoint (422)', function () {
        coaActor();
        $account = coaMake();

        $this->patchJson("/api/accounting/chart-of-accounts/{$account->uid}", [
            'normal_balance' => 'credit',
        ])->assertStatus(422)
            ->assertJsonValidationErrorFor('normal_balance');
    });

    it('returns 403 + ACCOUNT_LOCKED when editing a locked code via the endpoint', function () {
        coaActor();
        $account = coaMake(['code' => 'SYS-1', 'is_locked' => true]);

        $this->patchJson("/api/accounting/chart-of-accounts/{$account->uid}", [
            'code' => 'NEW-1',
        ])->assertStatus(403)
            ->assertJsonPath('error_code', 'ACCOUNT_LOCKED');
    });

    it('returns 409 + ACCOUNT_HAS_CHILDREN when deleting a non-empty group', function () {
        coaActor();
        $header = coaMake(['is_postable' => false]);
        coaMake(['parent_id' => $header->id, 'level' => 1]);

        $this->deleteJson("/api/accounting/chart-of-accounts/{$header->uid}")
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'ACCOUNT_HAS_CHILDREN');
    });

    it('returns 422 + REPARENT_CYCLE when moving an account under its descendant', function () {
        coaActor();
        $header = coaMake(['code' => 'H', 'is_postable' => false]);
        $child = coaMake(['code' => 'H-1', 'is_postable' => false, 'parent_id' => $header->id, 'level' => 1]);

        $this->postJson("/api/accounting/chart-of-accounts/{$header->uid}/move", [
            'parent_uid' => $child->uid,
        ])->assertStatus(422)
            ->assertJsonPath('error_code', 'REPARENT_CYCLE');
    });

    it('gets a single account via the endpoint (200)', function () {
        coaActor();
        $account = coaMake(['code' => '1-0009', 'name' => 'Petty Cash']);

        $this->getJson("/api/accounting/chart-of-accounts/{$account->uid}")
            ->assertOk()
            ->assertJsonPath('data.code', '1-0009')
            ->assertJsonMissingPath('data.children');
    });

    it('returns 404 for an unknown account via the endpoint', function () {
        coaActor();

        $this->getJson('/api/accounting/chart-of-accounts/does-not-exist')
            ->assertNotFound();
    });

    it('updates an account via the endpoint (200)', function () {
        coaActor();
        $account = coaMake(['code' => '1-0010', 'name' => 'Old Name']);

        $this->patchJson("/api/accounting/chart-of-accounts/{$account->uid}", [
            'name' => 'New Name',
        ])->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        assertDatabaseHas('accounts', ['id' => $account->id, 'name' => 'New Name']);
    });

    it('reparents an account via the move endpoint (200)', function () {
        coaActor();
        $from = coaMake(['code' => 'MF', 'is_postable' => false, 'account_type' => 'asset']);
        $to = coaMake(['code' => 'MT', 'is_postable' => false, 'account_type' => 'asset']);
        $leaf = coaMake(['code' => 'MF-1', 'account_type' => 'asset', 'parent_id' => $from->id, 'level' => 1]);

        $this->postJson("/api/accounting/chart-of-accounts/{$leaf->uid}/move", [
            'parent_uid' => $to->uid,
        ])->assertOk()
            ->assertJsonPath('data.parentUid', $to->uid);

        assertDatabaseHas('accounts', ['id' => $leaf->id, 'parent_id' => $to->id]);
    });

    it('activates and deactivates via the endpoints (200)', function () {
        coaActor();
        $account = coaMake(['is_active' => true]);

        $this->patchJson("/api/accounting/chart-of-accounts/{$account->uid}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.isActive', false);
        assertDatabaseHas('accounts', ['id' => $account->id, 'is_active' => false]);

        $this->patchJson("/api/accounting/chart-of-accounts/{$account->uid}/activate")
            ->assertOk()
            ->assertJsonPath('data.isActive', true);
    });

    it('deletes an account via the endpoint (200)', function () {
        coaActor();
        $account = coaMake(['code' => 'DEL-1']);

        $this->deleteJson("/api/accounting/chart-of-accounts/{$account->uid}")
            ->assertOk()
            ->assertJsonPath('message', __('accounting.coa.deleted'));

        assertSoftDeleted('accounts', ['id' => $account->id]);
    });

    it('suggests the next code via the endpoint (200)', function () {
        coaActor();
        $parent = coaMake(['code' => '1-100', 'is_postable' => false]);
        coaMake(['code' => '1-1001', 'parent_id' => $parent->id, 'level' => 1]);

        $this->getJson('/api/accounting/chart-of-accounts/suggest-code?parent_uid='.$parent->uid)
            ->assertOk()
            ->assertJsonPath('data.code', '1-1002');
    });

    it('suggests a top-level code from the account type via the endpoint (200)', function () {
        coaActor();

        $this->getJson('/api/accounting/chart-of-accounts/suggest-code?account_type=revenue')
            ->assertOk()
            ->assertJsonPath('data.code', '4-000');
    });

    it('requires account_type when no parent is given (422)', function () {
        coaActor();

        $this->getJson('/api/accounting/chart-of-accounts/suggest-code')
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('account_type');
    });

    it('forbids suggest-code without the create_coa permission (403)', function () {
        coaActor(['list_coa']);

        $this->getJson('/api/accounting/chart-of-accounts/suggest-code?account_type=asset')
            ->assertForbidden();
    });

    it('forbids access without the list_coa permission (403)', function () {
        coaActor([]);

        $this->getJson('/api/accounting/chart-of-accounts')->assertForbidden();
    });
});
