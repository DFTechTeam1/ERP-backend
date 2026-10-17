<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Finance\Database\Factories\DfEngineMenuFactory;

class DfEngineMenu extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'name',
        'description',
        'is_active',
        'created_by',
        'updated_by'
    ];

    // protected static function newFactory(): DfEngineMenuFactory
    // {
    //     // return DfEngineMenuFactory::new();
    // }
}
