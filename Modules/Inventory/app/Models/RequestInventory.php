<?php

namespace Modules\Inventory\Models;

use App\Enums\Inventory\RequestInventoryStatus;
use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Hrd\Models\Employee;
use Modules\Inventory\Database\Factories\RequestInventoryFactory;

class RequestInventory extends Model
{
    use HasFactory, ModelObserver;

    protected static function newFactory(): RequestInventoryFactory
    {
        //return RequestInventoryFactory::new();
    }

    protected bool $formatPrice = true;

    protected $fillable = [
        'uid',
        'name',
        'description',
        'price',
        'quantity',
        'purchase_source',
        'purchase_link',
        'status',
        'requested_by',
        'approval_target',
        'store_name'
    ];

    protected $appends = ['status_text', 'status_color', 'target_line'];

    protected function purchaseLink(): Attribute
    {
        return Attribute::make(
            get: fn($value) => json_decode($value, true),
            set: fn($value) => json_encode($value)
        );
    }

    protected function approvalTarget(): Attribute
    {
        return Attribute::make(
            get: fn($value) => json_decode($value, true),
            set: fn($value) => json_encode($value)
        );
    }

    protected function price(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $this->formatPrice ? config('company.currency') . ' ' . number_format($value, '0', '.', config('company.pricing_divider')) : $value,
        );
    }

    public function withoutFormattingPrice()
    {
        $this->formatPrice = false;
        return $this;
    }

    public function targetLine(): Attribute
    {
        $out = [];

        if (
            (isset($this->attributes['approval_target']))
        ) {
            $ids = json_decode($this->attributes['approval_target'], true);
            foreach ($ids as $id) {
                $employee = Employee::select('nickname')
                    ->find($id);

                $out[] = $employee->nickname;
            }
        }

        return Attribute::make(
            get: fn() => $out
        );
    }

    public function statusText(): Attribute
    {
        $out = '-';
        if (isset($this->attributes['status'])) {
            $cases = RequestInventoryStatus::cases();
            foreach ($cases as $case) {
                if ($case->value == $this->attributes['status']) {
                    $out = $case->label();
                    break;
                }
            }
        }

        return Attribute::make(
            get: fn() => $out
        );
    }

    public function statusColor(): Attribute
    {
        $out = '-';
        if (isset($this->attributes['status'])) {
            $cases = RequestInventoryStatus::cases();
            foreach ($cases as $case) {
                if ($case->value == $this->attributes['status']) {
                    $out = $case->badgeColor();
                    break;
                }
            }
        }

        return Attribute::make(
            get: fn() => $out
        );
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requested_by');
    }
}
