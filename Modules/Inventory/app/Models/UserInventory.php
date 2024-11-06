<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Hrd\Models\Employee;
use Modules\Inventory\Database\Factories\UserInventoryFactory;

class UserInventory extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'inventory_id',
        'user_inventory_master_id',
        'quantity'
    ];

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_id');
    }
}
