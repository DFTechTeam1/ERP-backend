<?php

namespace Modules\Inventory\Models;

use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Inventory\Database\factories\SupplierFactory;

class Supplier extends Model
{
    use HasFactory, ModelObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['uid', 'name'];

    protected $hidden = ['id'];
    
    protected static function newFactory(): SupplierFactory
    {
        //return SupplierFactory::new();
    }
}
