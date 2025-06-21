<?php

namespace Modules\Inventory\Models;

use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Inventory\Database\factories\BrandFactory;

class Brand extends Model
{
    use HasFactory, ModelObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['uid', 'name'];

    protected $hidden = ['id'];

    protected static function newFactory(): BrandFactory
    {
        // return BrandFactory::new();
    }
}
