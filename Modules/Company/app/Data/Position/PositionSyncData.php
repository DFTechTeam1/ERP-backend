<?php

namespace Modules\Company\Data\Position;

use Spatie\LaravelData\Data;

/**
 * Confirmed actions the user picked from the position sync preview.
 *
 * Only identifiers are sent — never field values — so the server always applies
 * authoritative data re-fetched from Greatday and can never be tricked into
 * writing client-supplied names or codes.
 */
class PositionSyncData extends Data
{
    public function __construct(
        /** @var array<int, int> Greatday positionId values to create in the ERP */
        public array $create = [],
        /** @var array<int, int> Greatday positionId values whose ERP row should be updated */
        public array $update = [],
        /** @var array<int, string> ERP position uids to archive (soft delete), guarded by reference checks */
        public array $delete = [],
    ) {}

    /**
     * The three buckets are independently optional — an empty array is valid and
     * means "no action of this kind" — so none of them may be flagged required.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'create' => ['sometimes', 'array'],
            'create.*' => ['integer'],
            'update' => ['sometimes', 'array'],
            'update.*' => ['integer'],
            'delete' => ['sometimes', 'array'],
            'delete.*' => ['string'],
        ];
    }
}
