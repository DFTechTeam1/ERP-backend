<?php

namespace Modules\Addon\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Addon\Database\Factories\AddonFactory;

class Addon extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'description',
        'preview_img',
        'tutorial_video',
        'main_file',
    ];

    protected static function newFactory(): AddonFactory
    {
        //return AddonFactory::new();
    }
}
