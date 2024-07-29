<?php

namespace Modules\Inventory\Models;

use App\Traits\ModelCreationObserver;
use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Inventory\Database\factories\InventoryFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Modules\Inventory\Observers\InventoryObserver;

// #[ObservedBy([InventoryObserver::class])]
class Inventory extends Model
{
    use HasFactory, ModelObserver, ModelCreationObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'name',
        'item_type',
        'brand_id',
        'supplier_id',
        'unit_id',
        'description',
        'warranty',
        'year_of_purchase',
        'purchase_price',
        'created_by',
        'updated_by',
        'stock',
        'warehouse_id'
    ];

    protected $appends = ['display_image'];
    
    // protected static function newFactory(): InventoryFactory
    // {
    //     //return InventoryFactory::new();
    // }

    public function images(): HasMany
    {
        return $this->hasMany(InventoryImage::class, 'inventory_id');
    }

    public function image(): HasOne
    {
        return $this->hasOne(InventoryImage::class, 'inventory_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'inventory_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function itemTypeRelation(): BelongsTo
    {
        return $this->belongsTo(InventoryType::class, 'item_type');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function displayImage(): Attribute
    {
        $out = asset('images/noimage.png');

        if (count($this->images) > 0) {
            $out = asset("storage/inventory/{$this->images[0]->image}");
        }
        return new Attribute(
            get: fn() => $out
        );
    }
}
