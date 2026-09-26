<?php

namespace App\Data\Finance\CostCenter;

use App\Enums\Finance\CostCenter\CostCenterType;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

class UpdateCostCenterData extends Data
{
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly CostCenterType $type,
        public readonly ?string $parent_uid,
        public readonly ?bool $is_active
    ) {}

    public static function rules(): array
    {
        return [
            'code' => ['string', 'required', Rule::unique('cost_centers', 'code')->ignore(request('costCenterUid'), 'uid')],
            'name' => ['string', 'required', Rule::unique('cost_centers', 'name')->ignore(request('costCenterUid'), 'uid')],
            'type' => 'required',
            'parent_uid' => 'nullable',
            'is_active' => 'nullable|bool',
        ];
    }
}
