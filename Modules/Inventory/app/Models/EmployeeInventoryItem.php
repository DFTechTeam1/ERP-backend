<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\EmployeeInventoryItemFactory;

class EmployeeInventoryItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'employee_inventory_master_id',
        'inventory_item_id',
        'inventory_status'
    ];

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function master(): BelongsTo
    {
        return $this->belongsTo(EmployeeInventoryMaster::class, 'employee_inventory_master_id');
    }
}
