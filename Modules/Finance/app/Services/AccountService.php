<?php

namespace Modules\Finance\Services;

use App\Data\Finance\Account\AccountData;
use App\Data\Finance\Account\MoveAccountData;
use App\Data\Finance\Account\StoreAccountData;
use App\Data\Finance\Account\SuggestAccountCodeData;
use App\Data\Finance\Account\UpdateAccountData;
use App\Enums\Accounting\AccountSubtype;
use App\Enums\Accounting\AccountType;
use App\Enums\Accounting\NormalBalance;
use App\Enums\ErrorCode\Code;
use App\Enums\System\BaseRole;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Exceptions\Accounting\AccountRuleException;
use Modules\Finance\Models\Account;
use Modules\Finance\Repository\AccountRepository;
use Spatie\LaravelData\Optional;

/**
 * Chart of Accounts service. Implements the Chart of Accounts API contract
 * (docs/chart-of-accounts-api-contract.md): tree/flat listing, CRUD, reparent,
 * activate/deactivate, and the server-authoritative editability rules (§4),
 * normal_balance derivation (§5) and error-code catalog (§6).
 *
 * `balance` and `has_transactions` depend on the general ledger (journal_lines),
 * which is not built yet, so {@see balance()} / {@see hasTransactions()} are the
 * single wiring points that return 0 / false until the ledger lands.
 */
class AccountService
{
    /**
     * @param  AccountRepository  $repo  Repository used to query and persist chart of accounts rows.
     */
    public function __construct(
        private readonly AccountRepository $repo
    ) {}

    /**
     * List the whole chart. `view=tree` (default) nests via `children` and
     * includes ancestors of search/filter matches so the tree stays connected;
     * `view=flat` returns the matching rows in code order with parentUid/level.
     *
     * @return array{error: bool, message: string, data: array<int, array<string, mixed>>, code: int}
     */
    public function list(): array
    {
        $view = request('view', 'tree');
        $search = request('search');
        $type = request('type');
        $active = request('active');

        $all = $this->repo->get([
            'with' => ['parent:id,uid'],
            'orderBy' => ['code' => 'asc'],
        ]);

        $activeFilter = ($active === null || $active === '')
            ? null
            : filter_var($active, FILTER_VALIDATE_BOOLEAN);

        $matches = $all->filter(function (Account $account) use ($type, $activeFilter, $search): bool {
            if ($type && $account->account_type->value !== $type) {
                return false;
            }
            if ($activeFilter !== null && (bool) $account->is_active !== $activeFilter) {
                return false;
            }
            if ($search && stripos($account->code, (string) $search) === false && stripos($account->name, (string) $search) === false) {
                return false;
            }

            return true;
        });

        $data = $view === 'flat'
            ? $this->buildFlat($matches)
            : $this->buildTree($all, $matches);

        return generalResponse(
            message: __('global.success'),
            data: $data,
            code: Code::Success->value,
        );
    }

    /**
     * Suggest the next account code so the create form can prefill it (the user
     * may still type their own). With a parent_uid the suggestion continues that
     * parent's children; otherwise it is a top-level code for the account_type.
     * Always returns a code not already in use.
     *
     * @return array{error: bool, message: string, data: array{code: string}, code: int}
     */
    public function suggestCode(SuggestAccountCodeData $payload): array
    {
        if ($payload->parent_uid) {
            $parent = $this->repo->show([
                'where' => ['uid' => $payload->parent_uid],
                'select' => ['id', 'code'],
            ]);
            if (! $parent) {
                throw ValidationException::withMessages(['parent_uid' => [__('accounting.coa.parentNotFound')]]);
            }

            $siblingCodes = $this->repo->get([
                'where' => ['parent_id' => $parent->id],
                'select' => ['code'],
            ])->pluck('code')->all();

            $seed = $parent->code.'1';
        } else {
            $siblingCodes = $this->repo->get([
                'where' => ['parent_id' => null, 'account_type' => $payload->account_type->value],
                'select' => ['code'],
            ])->pluck('code')->all();

            $seed = $this->typeDigit($payload->account_type).'-000';
        }

        $code = $this->nextCode($siblingCodes, $seed);

        while ($this->repo->show(['where' => ['code' => $code], 'select' => ['id']])) {
            $code = $this->incrementCode($code);
        }

        return generalResponse(
            message: __('global.success'),
            data: ['code' => $code],
            code: Code::Success->value,
        );
    }

    /**
     * Fetch a single account resource (no `children`).
     *
     * @return array{error: bool, message: string, data: array<string, mixed>, code: int}
     */
    public function detail(string $uid): array
    {
        $account = $this->resolve($uid);

        return generalResponse(
            message: __('global.success'),
            data: $this->toResource($account, $account->parent?->uid, new Optional, $this->computeBalance($account))->toArray(),
            code: Code::Success->value,
        );
    }

    /**
     * Create an account. Derives normal_balance (§5) and level server-side;
     * validates the parent (exists, is a header, same type) per §3.3.
     *
     * @return array{error: bool, message: string, data: array<string, mixed>, code: int}
     */
    public function store(StoreAccountData $payload): array
    {
        $parent = $this->resolveParentForCreate($payload);
        $isPostable = $payload->is_postable ?? true;

        $account = $this->repo->store([
            'code' => $payload->code,
            'name' => $payload->name,
            'account_type' => $payload->account_type,
            'account_subtype' => $payload->account_subtype,
            'parent_id' => $parent?->id,
            'level' => $parent ? ((int) $parent->level + 1) : 0,
            'is_postable' => $isPostable,
            'is_cash_equivalent' => (bool) $payload->is_cash_equivalent,
            'cash_flow_category' => $payload->cash_flow_category,
            'normal_balance' => $isPostable ? $this->deriveNormalBalance($payload->account_type, $payload->account_subtype) : null,
            'is_active' => true,
            'is_locked' => false,
        ]);

        $account->setRelation('parent', $parent);

        return generalResponse(
            message: __('accounting.coa.saved'),
            data: $this->toResource($account, $parent?->uid, new Optional, $this->computeBalance($account))->toArray(),
            code: Code::Created->value,
        );
    }

    /**
     * Update cosmetic/identity fields (not parent — see {@see move()}). Enforces
     * the per-field matrix (§4.B) and recomputes normal_balance on any accepted
     * type/subtype/postable change (§5).
     *
     * @return array{error: bool, message: string, data: array<string, mixed>, code: int}
     */
    public function update(UpdateAccountData $payload, string $uid): array
    {
        $account = $this->resolve($uid);
        $hasTransactions = $this->hasTransactions($account);
        $attributes = [];

        if (! ($payload->name instanceof Optional)) {
            $attributes['name'] = $payload->name;
        }
        if (! ($payload->is_cash_equivalent instanceof Optional)) {
            $attributes['is_cash_equivalent'] = $payload->is_cash_equivalent;
        }
        if (! ($payload->cash_flow_category instanceof Optional)) {
            $attributes['cash_flow_category'] = $payload->cash_flow_category;
        }

        if (! ($payload->code instanceof Optional) && $payload->code !== $account->code) {
            $this->guardIdentityEditable($account, __('accounting.field.code'), $hasTransactions);
            $attributes['code'] = $payload->code;
        }

        if (! ($payload->account_type instanceof Optional) && $payload->account_type !== $account->account_type) {
            $this->guardIdentityEditable($account, __('accounting.field.account_type'), $hasTransactions);
            $attributes['account_type'] = $payload->account_type;
        }

        if (! ($payload->is_postable instanceof Optional) && (bool) $payload->is_postable !== (bool) $account->is_postable) {
            if ($payload->is_postable === false) {
                if ($hasTransactions) {
                    throw AccountRuleException::leafToHeaderWithTxns();
                }
            } elseif ($account->children()->exists()) {
                throw AccountRuleException::hasChildren();
            }
            $attributes['is_postable'] = (bool) $payload->is_postable;
        }

        if (! ($payload->is_active instanceof Optional) && (bool) $payload->is_active !== (bool) $account->is_active) {
            if ($payload->is_active === false && $this->computeBalance($account) !== 0) {
                throw AccountRuleException::hasBalance();
            }
            $attributes['is_active'] = (bool) $payload->is_active;
        }

        $finalType = $attributes['account_type'] ?? $account->account_type;
        $finalIsPostable = $attributes['is_postable'] ?? (bool) $account->is_postable;
        $subtypeProvided = ! ($payload->account_subtype instanceof Optional);
        $finalSubtype = $subtypeProvided ? $payload->account_subtype : $account->account_subtype;

        if ($subtypeProvided && $finalSubtype !== $account->account_subtype) {
            $newNormalBalance = $finalIsPostable ? $this->deriveNormalBalance($finalType, $finalSubtype) : null;
            $sameTypeAndBalance = $finalType === $account->account_type && $newNormalBalance === $account->normal_balance;
            if (! $sameTypeAndBalance && $hasTransactions) {
                throw AccountRuleException::hasTransactions(__('accounting.field.account_subtype'));
            }
            $attributes['account_subtype'] = $finalSubtype;
        }

        if (isset($attributes['account_type']) || isset($attributes['account_subtype']) || isset($attributes['is_postable'])) {
            $attributes['normal_balance'] = $finalIsPostable ? $this->deriveNormalBalance($finalType, $finalSubtype) : null;
        }

        if ($attributes !== []) {
            $this->repo->update($account, $attributes);
        }

        return generalResponse(
            message: __('accounting.coa.saved'),
            data: $this->toResource($account, $account->parent?->uid, new Optional, $this->computeBalance($account))->toArray(),
            code: Code::Success->value,
        );
    }

    /**
     * Reparent an account (and its subtree) following the §3.5 validation order.
     * Recomputes level for the account and every descendant.
     *
     * @return array{error: bool, message: string, data: array<string, mixed>, code: int}
     */
    public function move(MoveAccountData $payload, string $uid): array
    {
        $account = $this->resolve($uid);

        if ($account->is_locked) {
            throw AccountRuleException::lockedGeneric();
        }

        $newParent = null;
        $newLevel = 0;

        if ($payload->parent_uid !== null) {
            $newParent = $this->repo->show(['where' => ['uid' => $payload->parent_uid]]);
            if (! $newParent) {
                throw ValidationException::withMessages(['parent_uid' => [__('accounting.coa.parentNotFound')]]);
            }
            if ($newParent->account_type !== $account->account_type) {
                throw AccountRuleException::reparentTypeMismatch();
            }
            if ($newParent->is_postable) {
                throw AccountRuleException::reparentOntoPostable();
            }
            if ($this->isSelfOrDescendant($account, $newParent)) {
                throw AccountRuleException::reparentCycle();
            }
            $newLevel = (int) $newParent->level + 1;
        }

        $warning = false;
        if ($this->hasTransactions($account)) {
            if (! $this->isAdmin()) {
                throw AccountRuleException::hasTransactions(__('accounting.field.parent'));
            }
            $warning = true;
        }

        $this->repo->update($account, [
            'parent_id' => $newParent?->id,
            'level' => $newLevel,
        ]);

        foreach ($account->children()->get() as $child) {
            $this->recomputeLevels($child, $newLevel + 1);
        }

        $account->setRelation('parent', $newParent);
        $data = $this->toResource($account, $newParent?->uid, new Optional, $this->computeBalance($account))->toArray();

        if ($warning) {
            $data['warning'] = __('accounting.coa.reparentWarning');
        }

        return generalResponse(
            message: __('accounting.coa.saved'),
            data: $data,
            code: Code::Success->value,
        );
    }

    /**
     * Mark an account active.
     *
     * @return array{error: bool, message: string, data: array<string, mixed>, code: int}
     */
    public function activate(string $uid): array
    {
        $account = $this->resolve($uid);

        $this->repo->update($account, ['is_active' => true]);

        return generalResponse(
            message: __('accounting.coa.activated'),
            data: $this->toResource($account, $account->parent?->uid, new Optional, $this->computeBalance($account))->toArray(),
            code: Code::Success->value,
        );
    }

    /**
     * Mark an account inactive. Requires a zero balance (§3.6).
     *
     * @return array{error: bool, message: string, data: array<string, mixed>, code: int}
     */
    public function deactivate(string $uid): array
    {
        $account = $this->resolve($uid);

        if ($this->computeBalance($account) !== 0) {
            throw AccountRuleException::hasBalance();
        }

        $this->repo->update($account, ['is_active' => false]);

        return generalResponse(
            message: __('accounting.coa.deactivated'),
            data: $this->toResource($account, $account->parent?->uid, new Optional, $this->computeBalance($account))->toArray(),
            code: Code::Success->value,
        );
    }

    /**
     * Soft-delete an empty, unlocked, transaction-free account (§3.7).
     *
     * @return array{error: bool, message: string, data: array<string, mixed>, code: int}
     */
    public function delete(string $uid): array
    {
        $account = $this->resolve($uid);

        if ($account->is_locked) {
            throw AccountRuleException::lockedGeneric();
        }
        if ($account->children()->exists()) {
            throw AccountRuleException::hasChildren();
        }
        if ($this->hasTransactions($account) || $this->computeBalance($account) !== 0) {
            throw AccountRuleException::hasTransactions(__('accounting.field.code'));
        }

        $this->repo->delete($account);

        return generalResponse(
            message: __('accounting.coa.deleted'),
            code: Code::Success->value,
        );
    }

    /**
     * Resolve an account by uid (with its parent's uid eager-loaded) or 404.
     */
    protected function resolve(string $uid): Account
    {
        $account = $this->repo->show([
            'where' => ['uid' => $uid],
            'with' => ['parent:id,uid'],
        ]);

        if (! $account) {
            throw AccountRuleException::notFound();
        }

        return $account;
    }

    /**
     * Validate and resolve the optional parent for a create (§3.3): must exist,
     * be a header, and share the account_type.
     *
     * @throws ValidationException
     */
    protected function resolveParentForCreate(StoreAccountData $payload): ?Account
    {
        if (! $payload->parent_uid) {
            return null;
        }

        $parent = $this->repo->show(['where' => ['uid' => $payload->parent_uid]]);

        if (! $parent) {
            throw ValidationException::withMessages(['parent_uid' => [__('accounting.coa.parentNotFound')]]);
        }
        if ($parent->is_postable) {
            throw ValidationException::withMessages(['parent_uid' => [__('accounting.coa.parentNotHeader')]]);
        }
        if ($parent->account_type !== $payload->account_type) {
            throw ValidationException::withMessages(['parent_uid' => [__('accounting.coa.parentTypeMismatch')]]);
        }

        return $parent;
    }

    /**
     * code / account_type are editable only when the account is neither locked
     * nor carrying posted transactions (§4.B).
     */
    protected function guardIdentityEditable(Account $account, string $field, bool $hasTransactions): void
    {
        if ($account->is_locked) {
            throw AccountRuleException::locked($field);
        }
        if ($hasTransactions) {
            throw AccountRuleException::hasTransactions($field);
        }
    }

    /**
     * normal_balance derivation (§5): base side by type, with contra subtypes
     * inverting it. Never accepted from the client.
     */
    protected function deriveNormalBalance(AccountType $type, ?AccountSubtype $subtype): NormalBalance
    {
        $contra = match ($subtype) {
            AccountSubtype::AccumulatedDepreciation => NormalBalance::Credit,
            AccountSubtype::OwnerDrawing => NormalBalance::Debit,
            default => null,
        };

        if ($contra !== null) {
            return $contra;
        }

        return match ($type) {
            AccountType::Asset, AccountType::Expense => NormalBalance::Debit,
            AccountType::Liability, AccountType::Equity, AccountType::Revenue => NormalBalance::Credit,
        };
    }

    /**
     * Pick the next code from a set of sibling codes by incrementing the highest
     * trailing number (preserving its format), or fall back to $seed when the
     * group is empty.
     *
     * @param  array<int, string>  $siblingCodes
     */
    protected function nextCode(array $siblingCodes, string $seed): string
    {
        $highest = null;
        $highestValue = -1;

        foreach ($siblingCodes as $code) {
            if (preg_match('/(\d+)(?!.*\d)/', (string) $code, $match)) {
                $value = (int) $match[1];
                if ($value >= $highestValue) {
                    $highestValue = $value;
                    $highest = (string) $code;
                }
            }
        }

        return $highest === null ? $seed : $this->incrementCode($highest);
    }

    /**
     * Increment the last run of digits in a code by one, zero-padded to its width
     * (e.g. 1-1003 -> 1-1004, 1-1009 -> 1-1010).
     */
    protected function incrementCode(string $code): string
    {
        return preg_replace_callback('/(\d+)(?!.*\d)/', function (array $match): string {
            $next = (string) ((int) $match[1] + 1);

            return str_pad($next, strlen($match[1]), '0', STR_PAD_LEFT);
        }, $code, 1);
    }

    /**
     * Leading code digit conventionally used per account type (asset 1 … expense 5).
     */
    protected function typeDigit(AccountType $type): string
    {
        return match ($type) {
            AccountType::Asset => '1',
            AccountType::Liability => '2',
            AccountType::Equity => '3',
            AccountType::Revenue => '4',
            AccountType::Expense => '5',
        };
    }

    /**
     * Whether `account` is `candidateParent` itself or one of its ancestors —
     * i.e. nesting under `candidateParent` would create a cycle.
     */
    protected function isSelfOrDescendant(Account $account, Account $candidateParent): bool
    {
        $node = $candidateParent;

        while ($node) {
            if ($node->id === $account->id) {
                return true;
            }
            $node = $node->parent_id
                ? $this->repo->show(['where' => ['id' => $node->parent_id]])
                : null;
        }

        return false;
    }

    /**
     * Depth-first level recompute for a moved subtree.
     */
    protected function recomputeLevels(Account $account, int $level): void
    {
        $this->repo->update($account, ['level' => $level]);

        foreach ($account->children()->get() as $child) {
            $this->recomputeLevels($child, $level + 1);
        }
    }

    protected function isAdmin(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasRole([BaseRole::Root->value, BaseRole::Director->value], 'sanctum');
    }

    /**
     * Leaf balance in IDR. Stub until the general ledger (journal_lines) exists.
     */
    protected function balance(Account $account): int
    {
        return 0;
    }

    /**
     * Whether the account has any posted journal line. Stub until the general
     * ledger (journal_lines) exists.
     */
    protected function hasTransactions(Account $account): bool
    {
        return false;
    }

    /**
     * Balance for a node: own balance for a leaf, sum of descendants for a header.
     */
    protected function computeBalance(Account $account): int
    {
        if ($account->is_postable) {
            return $this->balance($account);
        }

        $sum = 0;
        foreach ($account->children()->get() as $child) {
            $sum += $this->computeBalance($child);
        }

        return $sum;
    }

    /**
     * @param  array<int, AccountData>|Optional  $children
     */
    protected function toResource(Account $account, ?string $parentUid, array|Optional $children, int $balance): AccountData
    {
        return new AccountData(
            uid: $account->uid,
            code: $account->code,
            name: $account->name,
            accountType: $account->account_type,
            accountSubtype: $account->account_subtype,
            normalBalance: $account->normal_balance,
            parentUid: $parentUid,
            level: (int) $account->level,
            isPostable: (bool) $account->is_postable,
            isActive: (bool) $account->is_active,
            isLocked: (bool) $account->is_locked,
            hasTransactions: $this->hasTransactions($account),
            isCashEquivalent: $account->is_cash_equivalent === null ? null : (bool) $account->is_cash_equivalent,
            cashFlowCategory: $account->cash_flow_category,
            balance: $balance,
            children: $children,
        );
    }

    /**
     * Flat view: matching rows in code order, each with parentUid/level, no children.
     *
     * @param  Collection<int, Account>  $matches
     * @return array<int, array<string, mixed>>
     */
    protected function buildFlat(Collection $matches): array
    {
        return $matches
            ->map(fn (Account $account) => $this->toResource($account, $account->parent?->uid, new Optional, $this->computeBalance($account))->toArray())
            ->values()
            ->all();
    }

    /**
     * Tree view: nest matches plus their ancestors so the tree stays connected.
     *
     * @param  Collection<int, Account>  $all
     * @param  Collection<int, Account>  $matches
     * @return array<int, array<string, mixed>>
     */
    protected function buildTree(Collection $all, Collection $matches): array
    {
        $byId = $all->keyBy('id');
        $included = [];

        foreach ($matches as $account) {
            $node = $account;
            while ($node && ! isset($included[$node->id])) {
                $included[$node->id] = true;
                $node = $node->parent_id ? $byId->get($node->parent_id) : null;
            }
        }

        $childrenMap = [];
        $roots = [];

        foreach ($all as $account) {
            if (! isset($included[$account->id])) {
                continue;
            }
            if ($account->parent_id !== null && isset($included[$account->parent_id])) {
                $childrenMap[$account->parent_id][] = $account;
            } else {
                $roots[] = $account;
            }
        }

        return array_map(fn (Account $root) => $this->buildTreeNode($root, $childrenMap)->toArray(), $roots);
    }

    /**
     * @param  array<int, array<int, Account>>  $childrenMap  parent_id => ordered child accounts
     */
    protected function buildTreeNode(Account $account, array $childrenMap): AccountData
    {
        $children = [];
        $childrenBalance = 0;

        foreach ($childrenMap[$account->id] ?? [] as $child) {
            $node = $this->buildTreeNode($child, $childrenMap);
            $children[] = $node;
            $childrenBalance += $node->balance;
        }

        $balance = $account->is_postable ? $this->balance($account) : $childrenBalance;

        return $this->toResource($account, $account->parent?->uid, $children, $balance);
    }
}
