<?php

namespace Modules\Production\Models;

use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

// use Modules\Production\Database\Factories\InteractiveProjectReferenceFactory;

class InteractiveProjectReference extends Model
{
    use HasFactory, ModelObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'project_id',
        'type',
        'media_path',
        'link',
        'link_name',
    ];

    // protected static function newFactory(): InteractiveProjectReferenceFactory
    // {
    //     // return InteractiveProjectReferenceFactory::new();
    // }

    protected $appends = [
        'real_media_path',
        'full_path',
    ];

    public function fullPath(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->media_path ? 'interactives/projects/references/'.$this->media_path : null
        );
    }

    public function realMediaPath(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->media_path ? Storage::url('interactives/projects/references/'.$this->media_path) : null
        );
    }
}
