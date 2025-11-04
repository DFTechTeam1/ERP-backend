<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Production\Database\Factories\InteractiveProjectTaskWorkstateFactory;

class InteractiveProjectTaskWorkstate extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    // protected static function newFactory(): InteractiveProjectTaskWorkstateFactory
    // {
    //     // return InteractiveProjectTaskWorkstateFactory::new();
    // }
}
