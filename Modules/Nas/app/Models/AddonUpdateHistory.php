<?php

namespace Modules\Nas\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Nas\Database\Factories\AddonUpdateHistoryFactory;

class AddonUpdateHistory extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'addon_id',
        'improvements',
        'created_by',
        'updated_by',
    ];

    // protected static function newFactory(): AddonUpdateHistoryFactory
    // {
    //     //return AddonUpdateHistoryFactory::new();
    // }
}
