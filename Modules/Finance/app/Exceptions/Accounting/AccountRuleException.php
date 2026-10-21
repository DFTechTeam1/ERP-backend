<?php

namespace Modules\Finance\Exceptions\Accounting;

use App\Enums\ErrorCode\Code;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Chart of Accounts business-rule rejection. Renders itself as the contract's
 * error envelope — `{ "message": ..., "error_code": ... }` (plus an optional
 * `warning`) — at the HTTP status the rule dictates (403 / 404 / 409 / 422).
 *
 * The named constructors mirror the §6 error-code catalog of the Chart of
 * Accounts API contract one-to-one.
 */
class AccountRuleException extends Exception
{
    /**
     * @param  array<string, mixed>  $extra  Extra top-level keys to merge into the JSON body (e.g. a warning).
     */
    private function __construct(
        string $message,
        private readonly int $status,
        private readonly ?string $errorCode = null,
        private readonly array $extra = [],
    ) {
        parent::__construct($message);
    }

    public static function notFound(): self
    {
        return new self(__('notification.accountNotFound'), Code::NotFound->value);
    }

    public static function locked(string $field): self
    {
        return new self(
            __('accounting.coa.locked', ['field' => $field]),
            Code::Forbidden->value,
            'ACCOUNT_LOCKED',
        );
    }

    public static function lockedGeneric(): self
    {
        return new self(
            __('accounting.coa.lockedGeneric'),
            Code::Forbidden->value,
            'ACCOUNT_LOCKED',
        );
    }

    public static function hasTransactions(string $field): self
    {
        return new self(
            __('accounting.coa.hasTransactions', ['field' => $field]),
            Code::Conflict->value,
            'ACCOUNT_HAS_TRANSACTIONS',
        );
    }

    public static function reparentTypeMismatch(): self
    {
        return new self(
            __('accounting.coa.reparentTypeMismatch'),
            Code::ValidationError->value,
            'REPARENT_TYPE_MISMATCH',
        );
    }

    public static function reparentOntoPostable(): self
    {
        return new self(
            __('accounting.coa.reparentOntoPostable'),
            Code::ValidationError->value,
            'REPARENT_ONTO_POSTABLE',
        );
    }

    public static function reparentCycle(): self
    {
        return new self(
            __('accounting.coa.reparentCycle'),
            Code::ValidationError->value,
            'REPARENT_CYCLE',
        );
    }

    public static function hasBalance(): self
    {
        return new self(
            __('accounting.coa.hasBalance'),
            Code::Conflict->value,
            'ACCOUNT_HAS_BALANCE',
        );
    }

    public static function hasChildren(): self
    {
        return new self(
            __('accounting.coa.hasChildren'),
            Code::Conflict->value,
            'ACCOUNT_HAS_CHILDREN',
        );
    }

    public static function leafToHeaderWithTxns(): self
    {
        return new self(
            __('accounting.coa.leafToHeaderWithTxns'),
            Code::Conflict->value,
            'LEAF_TO_HEADER_WITH_TXNS',
        );
    }

    /**
     * Attach a non-fatal warning that still rides a successful-shaped move; used
     * when a reparent is allowed after transactions under the admin override.
     */
    public function render(Request $request): JsonResponse
    {
        $body = ['message' => $this->getMessage()];

        if ($this->errorCode !== null) {
            $body['error_code'] = $this->errorCode;
        }

        return response()->json(array_merge($body, $this->extra), $this->status);
    }
}
