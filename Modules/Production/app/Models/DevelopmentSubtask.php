<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Production\Database\Factories\DevelopmentSubtaskFactory;

class DevelopmentSubtask extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    // protected static function newFactory(): DevelopmentSubtaskFactory
    // {
    //     // return DevelopmentSubtaskFactory::new();
    // }
}
