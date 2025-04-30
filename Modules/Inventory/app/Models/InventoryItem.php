<?php

namespace Modules\Inventory\Models;

use App\Enums\Inventory\InventoryStatus;
use App\Enums\Inventory\Location;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Inventory\Database\factories\InventoryItemFactory;

class InventoryItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'inventory_id',
        'inventory_code',
        'status',
        'current_location',
        'user_id',
        'qrcode',
        'purchase_price',
        'warranty',
        'year_of_purchase'
    ];

    // protected static function newFactory(): InventoryItemFactory
    // {
    //     //return InventoryItemFactory::new();
    // }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InventoryStatus::class,
        ];
    }

    protected $appends = [
        'status_text',
        'status_badge_color',
        'location',
        'location_badge'
    ];

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class, 'inventory_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(\Modules\Hrd\Models\Employee::class, 'user_id');
    }

    public function customInventory(): HasOne
    {
        return $this->hasOne(CustomInventoryDetail::class, 'inventory_id');
    }

    public function location(): Attribute
    {
        $out = '-';

        if ($this->current_location) {
            $locations = Location::cases();
            foreach ($locations as $location) {
                if ($location->value == $this->current_location) {
                    $out = $location->label();
                }
            }
        }

        return new Attribute(
            get: fn() => $out ?? 0
        );
    }

    public function locationBadge(): Attribute
    {
        $out = '-';

        if ($this->current_location) {
            $locations = Location::cases();
            foreach ($locations as $location) {
                if ($location->value == $this->current_location) {
                    $out = $location->badgeColor();
                }
            }
        }

        return new Attribute(
            get: fn() => $out
        );
    }

    public function statusText(): Attribute
    {
        $out = '-';

        if ($this->status) {
            $cases = InventoryStatus::cases();
            $search = array_search($this->status, $cases);
            $out = $cases[$search]->label();
        }

        return new Attribute(
            get: fn() => $out
        );
    }

    public function statusBadgeColor(): Attribute
    {
        $out = '';

        if ($this->status) {
            $cases = InventoryStatus::cases();
            $search = array_search($this->status, $cases);
            $out = $cases[$search]->badgeColor();
        }

        return new Attribute(
            get: fn() => $out,
        );
    }
}
