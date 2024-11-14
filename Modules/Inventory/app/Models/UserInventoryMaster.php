<?php

namespace Modules\Inventory\Models;

use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Hrd\Models\Employee;
use Modules\Inventory\Database\Factories\UserInventoryMasterFactory;

class UserInventoryMaster extends Model
{
    use HasFactory, ModelObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'employee_id',
        'uid',
        'total_inventory',
        'inventory_type'
    ];

    public function items(): HasMany
    {
        return $this->hasMany(UserInventory::class, 'user_inventory_master_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
