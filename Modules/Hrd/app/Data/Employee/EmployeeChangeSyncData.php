<?php

namespace Modules\Hrd\Data\Employee;

use Spatie\LaravelData\Data;

/**
 * The employees (by Greatday empId) the user confirmed to sync changes for.
 * Only identifiers are sent — the server re-fetches Greatday for authoritative values.
 */
class EmployeeChangeSyncData extends Data
{
    public function __construct(
        /** @var array<int, string> Greatday empId values */
        public array $employees = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'employees' => ['required', 'array', 'min:1'],
            'employees.*' => ['string'],
        ];
    }
}
