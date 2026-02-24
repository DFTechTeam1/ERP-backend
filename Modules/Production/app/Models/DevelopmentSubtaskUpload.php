<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Production\Database\Factories\DevelopmentSubtaskUploadFactory;

class DevelopmentSubtaskUpload extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    // protected static function newFactory(): DevelopmentSubtaskUploadFactory
    // {
    //     // return DevelopmentSubtaskUploadFactory::new();
    // }
}
