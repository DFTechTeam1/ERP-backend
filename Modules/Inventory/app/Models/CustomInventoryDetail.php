<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomInventoryDetail extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'custom_inventory_id',
        'inventory_id',
        'qty',
        'price',
    ];

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(\Modules\Inventory\Models\InventoryItem::class, 'inventory_id');
    }
}
