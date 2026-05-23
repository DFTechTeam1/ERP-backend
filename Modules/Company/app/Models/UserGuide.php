<?php

namespace Modules\Company\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Company\Database\Factories\UserGuideFactory;

class UserGuide extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'file_path'
    ];

    // protected static function newFactory(): UserGuideFactory
    // {
    //     // return UserGuideFactory::new();
    // }
}
