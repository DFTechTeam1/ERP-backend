<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Inventory\Database\factories\InventoryRequestItemFactory;

class InventoryRequestItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    protected static function newFactory(): InventoryRequestItemFactory
    {
        // return InventoryRequestItemFactory::new();
    }
}
