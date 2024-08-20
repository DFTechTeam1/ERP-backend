<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Production\Database\Factories\ProjectReferenceFactory;

class ProjectReference extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_id',
        'media_path',
        'name',
        'type',
        'folder',
    ];

    protected $appends = ['media_path_text'];

    public function mediaPathText(): Attribute
    {
        $out = '-';
        if ($this->media_path && $this->project_id) {
            $out = asset('storage/projects/references/' . $this->project_id . '/' . $this->media_path);
        }

        return Attribute::make(
            get: fn() => $out,
        );
    }
}
