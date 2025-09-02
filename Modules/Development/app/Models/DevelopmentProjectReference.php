<?php

namespace Modules\Development\Models;

use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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
        'link',
        'link_name',
    ];

    protected $appends = [
        'real_media_path',
        'full_path',
    ];

    // protected static function newFactory(): DevelopmentProjectReferenceFactory
    // {
    //     // return DevelopmentProjectReferenceFactory::new();
    // }

    public function fullPath(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->media_path ? 'development/projects/references/'.$this->media_path : null
        );
    }

    public function realMediaPath(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->media_path ? Storage::url('development/projects/references/'.$this->media_path) : null
        );
    }
}
