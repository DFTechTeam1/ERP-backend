<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Inventory\Database\Factories\EmployeeCustomInventoryFactory;

class EmployeeCustomInventory extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'employee_id',
        'custom_inventory_id'
    ];

    protected static function newFactory(): EmployeeCustomInventoryFactory
    {
        //return EmployeeCustomInventoryFactory::new();
    }
}
