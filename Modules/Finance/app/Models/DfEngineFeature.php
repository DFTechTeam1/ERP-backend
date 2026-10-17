<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Finance\Database\Factories\DfEngineFeatureFactory;

class DfEngineFeature extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'name',
        'type',
        'description',
        'is_active',
        'created_by',
        'updated_by'
    ];

    // protected static function newFactory(): DfEngineFeatureFactory
    // {
    //     // return DfEngineFeatureFactory::new();
    // }
}
