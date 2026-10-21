<?php

namespace Modules\Finance\Http\Controllers\Api;

use App\Data\Finance\Account\MoveAccountData;
use App\Data\Finance\Account\StoreAccountData;
use App\Data\Finance\Account\SuggestAccountCodeData;
use App\Data\Finance\Account\UpdateAccountData;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Finance\Exceptions\Accounting\AccountRuleException;
use Modules\Finance\Services\AccountService;

/**
 * Chart of Accounts (Bagan Akun) endpoints. See the API contract at
 * docs/chart-of-accounts-api-contract.md. Business-rule failures bubble out of
 * the service as self-rendering {@see AccountRuleException}
 * (correct status + error_code); validation failures render as 422 with an `errors` map.
 */
class AccountController extends Controller
{
    /**
     * @param  AccountService  $service  Service handling chart of accounts reads and writes.
     */
    public function __construct(
        private readonly AccountService $service
    ) {}

    /**
     * List the chart (tree or flat). Query: view, search, type, active.
     */
    public function index(): JsonResponse
    {
        return apiResponse($this->service->list());
    }

    /**
     * Suggest the next account code for the create form (manual entry still allowed).
     */
    public function suggestCode(SuggestAccountCodeData $request): JsonResponse
    {
        return apiResponse($this->service->suggestCode($request));
    }

    /**
     * Get a single account resource (no children).
     */
    public function show(string $uid): JsonResponse
    {
        return apiResponse($this->service->detail($uid));
    }

    /**
     * Create an account.
     */
    public function store(StoreAccountData $request): JsonResponse
    {
        return apiResponse($this->service->store($request));
    }

    /**
     * Update cosmetic/identity fields of an account.
     */
    public function update(UpdateAccountData $request, string $uid): JsonResponse
    {
        return apiResponse($this->service->update($request, $uid));
    }

    /**
     * Reparent an account (and its subtree).
     */
    public function move(MoveAccountData $request, string $uid): JsonResponse
    {
        return apiResponse($this->service->move($request, $uid));
    }

    /**
     * Mark an account active.
     */
    public function activate(string $uid): JsonResponse
    {
        return apiResponse($this->service->activate($uid));
    }

    /**
     * Mark an account inactive (requires a zero balance).
     */
    public function deactivate(string $uid): JsonResponse
    {
        return apiResponse($this->service->deactivate($uid));
    }

    /**
     * Soft-delete an empty, unlocked, transaction-free account.
     */
    public function destroy(string $uid): JsonResponse
    {
        return apiResponse($this->service->delete($uid));
    }
}
