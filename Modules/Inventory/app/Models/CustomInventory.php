<?php

namespace Modules\Inventory\Models;

use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Database\Factories\CustomInventoryFactory;

class CustomInventory extends Model
{
    use HasFactory, ModelObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'build_series',
        'name',
        'type',
        'location',
        'default_request_item',
        'barcode'
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function items(): HasMany
    {
        return $this->hasMany(\Modules\Inventory\Models\CustomInventoryDetail::class, 'custom_inventory_id');
    }
}
