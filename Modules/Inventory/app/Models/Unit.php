<?php

namespace Modules\Inventory\Models;

use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Inventory\Database\factories\UnitFactory;

class Unit extends Model
{
    use HasFactory, ModelObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'name',
    ];

    protected $hidden = ['id'];

    protected static function newFactory(): UnitFactory
    {
        // return UnitFactory::new();
    }
}
