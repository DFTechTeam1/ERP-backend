<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Finance\Database\Factories\DfEngineApiKeyFactory;

class DfEngineApiKey extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'expires_at',
        'uid',
        'limit',
        'limit_reset',
        'key',
        'hash',
        'name',
        'description',
        'employee_id',
        'employee_name',
        'is_main',
        'created_by',
        'updated_by'
    ];

    // protected static function newFactory(): DfEngineApiKeyFactory
    // {
    //     // return DfEngineApiKeyFactory::new();
    // }
}
