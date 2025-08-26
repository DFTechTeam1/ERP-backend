<?php

namespace Modules\Development\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Development\Database\Factories\DevelopmentTaskPicHistoryFactory;

class DevelopmentTaskPicHistory extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    // protected static function newFactory(): DevelopmentTaskPicHistoryFactory
    // {
    //     // return DevelopmentTaskPicHistoryFactory::new();
    // }
}
