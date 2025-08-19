<?php

namespace Modules\Development\Models;

use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Development\Database\Factories\DevelopmentProjectReferenceFactory;

class DevelopmentProjectReference extends Model
{
    use HasFactory, ModelObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'development_project_id',
        'type',
        'media_path',
        'link'
    ];

    // protected static function newFactory(): DevelopmentProjectReferenceFactory
    // {
    //     // return DevelopmentProjectReferenceFactory::new();
    // }
}
